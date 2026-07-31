<?php
/**
 * Training Pathways — event observer.
 *
 * Listens for core course_completed events and automatically marks any
 * pathway task that is linked to the completed course as complete.
 * Also auto-completes the whole assignment if every required task is now done.
 *
 * @package    local_trainingpathways
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_trainingpathways;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Fired when Moodle marks a student as having completed a course.
     * Automatically ticks any pathway task of type course/quiz that points
     * to the same course, for any active assignment the student has.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;

        // Release session lock — this runs inside the same request as the
        // completion trigger and we don't need the session for our DB writes.
        \core\session\manager::write_close();

        // The student who completed the course.
        // core\event\course_completed stores the user in relateduserid;
        // fall back to userid if relateduserid is not set (older Moodle builds).
        $userid   = $event->relateduserid ?: $event->userid;
        $courseid = (int)$event->courseid;

        if (!$userid || !$courseid) {
            return;
        }

        // Find every active assignment for this student.
        $assignments = $DB->get_records(
            'local_tp_assignments',
            ['userid' => $userid, 'status' => 'active']
        );

        if (empty($assignments)) {
            return;
        }

        $now = time();

        foreach ($assignments as $assignment) {

            // Find tasks in this pathway that are linked to the completed course.
            $sql = "SELECT t.*
                      FROM {local_tp_tasks} t
                      JOIN {local_tp_stages} s ON s.id = t.stageid
                     WHERE s.pathwayid = :pathwayid
                       AND t.tasktype IN ('course', 'quiz')
                       AND t.courseid  = :courseid";

            $tasks = $DB->get_records_sql($sql, [
                'pathwayid' => $assignment->pathwayid,
                'courseid'  => $courseid,
            ]);

            if (empty($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                $existing = $DB->get_record('local_tp_progress', [
                    'assignmentid' => $assignment->id,
                    'taskid'       => $task->id,
                ]);

                if ($existing) {
                    if ($existing->status === 'complete') {
                        continue; // Already done — nothing to update.
                    }
                    $DB->update_record('local_tp_progress', (object)[
                        'id'           => $existing->id,
                        'status'       => 'complete',
                        'signedoffby'  => null,
                        'timemodified' => $now,
                    ]);
                } else {
                    $DB->insert_record('local_tp_progress', (object)[
                        'assignmentid' => $assignment->id,
                        'taskid'       => $task->id,
                        'userid'       => $userid,
                        'status'       => 'complete',
                        'signedoffby'  => null,
                        'notes'        => null,
                        'timemodified' => $now,
                    ]);
                }
            }

            // ── Auto-complete the whole assignment if every required task is done ──
            $requiredTasks = $DB->get_records_sql(
                "SELECT t.id
                   FROM {local_tp_tasks} t
                   JOIN {local_tp_stages} s ON s.id = t.stageid
                  WHERE s.pathwayid = :pid
                    AND t.required  = 1",
                ['pid' => $assignment->pathwayid]
            );

            if (!empty($requiredTasks)) {
                $allDone = true;
                foreach ($requiredTasks as $rt) {
                    $prog = $DB->get_record('local_tp_progress', [
                        'assignmentid' => $assignment->id,
                        'taskid'       => $rt->id,
                    ]);
                    if (!$prog || $prog->status !== 'complete') {
                        $allDone = false;
                        break;
                    }
                }
                if ($allDone) {
                    $DB->set_field(
                        'local_tp_assignments',
                        'status',
                        'completed',
                        ['id' => $assignment->id]
                    );
                }
            }
        }
    }
}
