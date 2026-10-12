<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU GPL v3 or later.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.
namespace local_trainingpathways;

defined('MOODLE_INTERNAL') || die();

/**
 * Single permission and evidence boundary for manual progress changes.
 *
 * @package local_trainingpathways
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress_service {
    /** Idempotent active allocation; never reopens an historical attempt. */
    public static function allocate(int $pathwayid, int $userid, ?int $targetdate): int {
        global $DB, $USER;
        require_capability('local/trainingpathways:assign', \context_system::instance());
        $factory = \core\lock\lock_config::get_lock_factory('local_trainingpathways');
        $lock = $factory->get_lock('allocate_' . $pathwayid . '_' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $DB->get_record('local_tp_pathways', ['id' => $pathwayid, 'status' => 'active'], '*', MUST_EXIST);
            $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
            $existing = $DB->get_record('local_tp_assignments',
                ['pathwayid' => $pathwayid, 'userid' => $userid, 'status' => 'active']);
            if ($existing) {
                return (int)$existing->id;
            }
            return $DB->insert_record('local_tp_assignments', (object)[
                'pathwayid' => $pathwayid, 'userid' => $userid, 'assignedby' => $USER->id,
                'timeassigned' => time(), 'targetdate' => $targetdate, 'status' => 'active',
                'timemodified' => time(),
            ]);
        } finally {
            $lock->release();
        }
    }
    /** Reconcile a core completion under the same lock as manual marking/revocation. */
    public static function course_completed(int $assignmentid, int $courseid, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/trainingpathways/lib.php');
        $factory = \core\lock\lock_config::get_lock_factory('local_trainingpathways');
        $lock = $factory->get_lock('assignment_' . $assignmentid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $assignment = $DB->get_record('local_tp_assignments',
                ['id' => $assignmentid, 'userid' => $userid, 'status' => 'active']);
            $completion = $DB->get_record('course_completions', ['course' => $courseid, 'userid' => $userid]);
            // An event alone (including a replay after a reset) is not completion evidence.
            if (!$assignment || !$completion || empty($completion->timecompleted)) {
                $transaction->allow_commit();
                return;
            }
            $tasks = $DB->get_records_sql(
                "SELECT t.*
                   FROM {local_tp_tasks} t
                   JOIN {local_tp_stages} s ON s.id = t.stageid
                  WHERE s.pathwayid = :pathwayid AND t.courseid = :courseid
                    AND t.tasktype IN ('course', 'quiz')",
                ['pathwayid' => $assignment->pathwayid, 'courseid' => $courseid]);
            foreach ($tasks as $task) {
                $existing = $DB->get_record('local_tp_progress',
                    ['assignmentid' => $assignmentid, 'taskid' => $task->id]);
                if ($existing && $existing->status === 'complete') {
                    continue;
                }
                $record = (object)[
                    'assignmentid' => $assignmentid, 'taskid' => $task->id,
                    'userid' => $userid, 'status' => 'complete',
                    'signedoffby' => null, 'timemodified' => time(),
                ];
                if ($existing) {
                    $record->id = $existing->id;
                    $DB->update_record('local_tp_progress', $record);
                } else {
                    $DB->insert_record('local_tp_progress', $record);
                }
            }
            $progress = local_trainingpathways_assignment_progress($assignmentid);
            if ($progress['total'] > 0 && $progress['complete'] === $progress['total']) {
                $DB->update_record('local_tp_assignments', (object)[
                    'id' => $assignmentid, 'status' => 'completed', 'timemodified' => time(),
                ]);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /** Revoke access to an attempt without destroying its evidence. */
    public static function revoke(int $assignmentid): void {
        global $DB;
        require_capability('local/trainingpathways:assign', \context_system::instance());
        $factory = \core\lock\lock_config::get_lock_factory('local_trainingpathways');
        $lock = $factory->get_lock('assignment_' . $assignmentid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $DB->get_record('local_tp_assignments', ['id' => $assignmentid], '*', MUST_EXIST);
            $DB->update_record('local_tp_assignments', (object)[
                'id' => $assignmentid, 'status' => 'revoked', 'timemodified' => time(),
            ]);
        } finally {
            $lock->release();
        }
    }

    /** Validate both the relationship and the kind of evidence being changed. */
    public static function validate_change(\stdClass $assignment, \stdClass $task,
            \stdClass $stage, string $status, int $actorid, bool $cansignoff): void {
        $owner = (int)$assignment->userid === $actorid;
        if (!in_array($status, ['notstarted', 'inprogress', 'complete'], true)
                || (!$owner && !$cansignoff)
                || (int)$stage->id !== (int)$task->stageid
                || (int)$stage->pathwayid !== (int)$assignment->pathwayid
                || $assignment->status !== 'active'
                || in_array($task->tasktype, ['course', 'quiz'], true)
                || ($task->tasktype === 'signoff' && (!$cansignoff || $owner))) {
            throw new \moodle_exception('accessdenied', 'local_trainingpathways');
        }
    }

    /** Apply a manual change atomically; completed/revoked attempts are immutable. */
    public static function mark(int $assignmentid, int $taskid, string $status): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/local/trainingpathways/lib.php');
        $factory = \core\lock\lock_config::get_lock_factory('local_trainingpathways');
        $lock = $factory->get_lock('assignment_' . $assignmentid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $assignment = $DB->get_record('local_tp_assignments', ['id' => $assignmentid], '*', MUST_EXIST);
            $task = $DB->get_record('local_tp_tasks', ['id' => $taskid], '*', MUST_EXIST);
            $stage = $DB->get_record('local_tp_stages', ['id' => $task->stageid], '*', MUST_EXIST);
            self::validate_change($assignment, $task, $stage, $status, (int)$USER->id,
                has_capability('local/trainingpathways:signoff', \context_system::instance()));
            $existing = $DB->get_record('local_tp_progress',
                ['assignmentid' => $assignmentid, 'taskid' => $taskid]);
            $record = (object)[
                'assignmentid' => $assignmentid, 'taskid' => $taskid,
                'userid' => $assignment->userid, 'status' => $status,
                'signedoffby' => $task->tasktype === 'signoff' && $status === 'complete' ? $USER->id : null,
                'timemodified' => time(),
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_tp_progress', $record);
            } else {
                $DB->insert_record('local_tp_progress', $record);
            }
            $progress = local_trainingpathways_assignment_progress($assignmentid);
            if ($progress['total'] > 0 && $progress['complete'] === $progress['total']) {
                $DB->update_record('local_tp_assignments', (object)[
                    'id' => $assignmentid, 'status' => 'completed', 'timemodified' => time(),
                ]);
            }
            $transaction->allow_commit();
            return $progress;
        } finally {
            $lock->release();
        }
    }
}
