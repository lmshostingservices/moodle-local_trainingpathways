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
 * Reconcile verified Moodle course completion with active pathway attempts.
 *
 * @package local_trainingpathways
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** Handle completion; never use the initiating administrator as the learner. */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;
        $userid = (int)$event->relateduserid;
        $courseid = (int)$event->courseid;
        if (!$userid || !$courseid) {
            return;
        }
        $assignments = $DB->get_records('local_tp_assignments',
            ['userid' => $userid, 'status' => 'active'], '', 'id');
        foreach ($assignments as $assignment) {
            progress_service::course_completed((int)$assignment->id, $courseid, $userid);
        }
    }
}
