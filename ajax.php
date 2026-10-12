<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX handler for Training Pathways.
 *
 * Actions:
 *   mark_task   — employee/manager marks a task complete/incomplete
 *   signoff_task — manager signs off a sign-off task
 *   get_progress — returns JSON progress summary for an assignment
 * @package    local_trainingpathways
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
$action  = required_param('action', PARAM_ALPHA);

header('Content-Type: application/json');

try {
    switch ($action) {

        // ── Mark task complete / incomplete ───────────────────────────────
        case 'mark_task': {
            $assignmentid = required_param('assignmentid', PARAM_INT);
            $taskid       = required_param('taskid',       PARAM_INT);
            $newstatus    = required_param('status',       PARAM_ALPHA);

            $prog = \local_trainingpathways\progress_service::mark($assignmentid, $taskid, $newstatus);

            echo json_encode(['ok' => true, 'progress' => $prog]);
            break;
        }

        // ── Get progress summary ──────────────────────────────────────────
        case 'get_progress': {
            $assignmentid = required_param('assignmentid', PARAM_INT);
            $asgn = $DB->get_record('local_tp_assignments', ['id' => $assignmentid], '*', MUST_EXIST);
            if ((int)$asgn->userid !== (int)$USER->id
                && !has_capability('local/trainingpathways:viewall', $context)) {
                throw new moodle_exception('accessdenied', 'local_trainingpathways');
            }
            $prog = local_trainingpathways_assignment_progress($assignmentid);
            echo json_encode(['ok' => true, 'progress' => $prog]);
            break;
        }

        default:
            throw new moodle_exception('invalidparam', 'local_trainingpathways', '', 'Unknown action: ' . $action);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
