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

defined('MOODLE_INTERNAL') || die();

/**
 * Extend Moodle navigation with Training Pathways links.
 * @package    local_trainingpathways
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
function local_trainingpathways_extend_navigation(global_navigation $nav) {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $context = context_system::instance();
    $base = new moodle_url('/local/trainingpathways/');

    if (has_capability('local/trainingpathways:manage', $context)) {
        $node = $nav->add(
            get_string('trainingpathways', 'local_trainingpathways'),
            $base . 'manage.php',
            navigation_node::TYPE_CUSTOM,
            null, 'trainingpathways_manage',
            new pix_icon('i/competencies', '')
        );
    } elseif (has_capability('local/trainingpathways:viewall', $context)) {
        $nav->add(
            get_string('trainingpathways', 'local_trainingpathways'),
            $base . 'dashboard.php',
            navigation_node::TYPE_CUSTOM,
            null, 'trainingpathways_dashboard',
            new pix_icon('i/competencies', '')
        );
    } elseif (has_capability('local/trainingpathways:view', $context)) {
        $nav->add(
            get_string('trainingpathways', 'local_trainingpathways'),
            $base . 'view.php',
            navigation_node::TYPE_CUSTOM,
            null, 'trainingpathways_view',
            new pix_icon('i/competencies', '')
        );
    }
}

/**
 * Return all active assignments for a user, with pathway and stage data.
 */
function local_trainingpathways_get_user_assignments(int $userid): array {
    global $DB;
    return $DB->get_records_sql(
        "SELECT a.*, p.name AS pathwayname, p.colour AS pathwaycolour
           FROM {local_tp_assignments} a
           JOIN {local_tp_pathways} p ON p.id = a.pathwayid
          WHERE a.userid = :userid AND a.status = 'active'
          ORDER BY a.timeassigned DESC",
        ['userid' => $userid]
    );
}

/**
 * Calculate overall % progress for an assignment.
 */
function local_trainingpathways_assignment_progress(int $assignmentid): array {
    global $DB;

    // Count all tasks in the pathway for this assignment.
    $total = (int)$DB->count_records_sql(
        "SELECT COUNT(t.id)
           FROM {local_tp_tasks} t
           JOIN {local_tp_stages} s ON s.id = t.stageid
           JOIN {local_tp_assignments} a ON a.pathwayid = s.pathwayid
          WHERE a.id = :aid",
        ['aid' => $assignmentid]
    );

    if ($total === 0) {
        return ['total' => 0, 'complete' => 0, 'percent' => 0];
    }

    $complete = (int)$DB->count_records_sql(
        "SELECT COUNT(p.id)
           FROM {local_tp_progress} p
          WHERE p.assignmentid = :aid AND p.status = 'complete'",
        ['aid' => $assignmentid]
    );

    return [
        'total'   => $total,
        'complete'=> $complete,
        'percent' => (int)round(($complete / $total) * 100),
    ];
}

/**
 * Load all stages + tasks for a pathway, keyed by stage id.
 */
function local_trainingpathways_get_pathway_structure(int $pathwayid): array {
    global $DB;

    $stages = $DB->get_records('local_tp_stages', ['pathwayid' => $pathwayid], 'sortorder ASC');
    foreach ($stages as &$stage) {
        $stage->tasks = $DB->get_records('local_tp_tasks', ['stageid' => $stage->id], 'sortorder ASC');
    }
    unset($stage);
    return $stages;
}

/**
 * Load progress map for an assignment: taskid => progress record.
 */
function local_trainingpathways_get_progress_map(int $assignmentid): array {
    global $DB;
    $rows = $DB->get_records('local_tp_progress', ['assignmentid' => $assignmentid]);
    $map = [];
    foreach ($rows as $r) {
        $map[$r->taskid] = $r;
    }
    return $map;
}

/**
 * Determine the display status of a stage given task progress.
 * Returns: 'complete' | 'inprogress' | 'notstarted'
 */
function local_trainingpathways_stage_status(array $tasks, array $progressMap): string {
    if (empty($tasks)) {
        return 'notstarted';
    }
    $total    = count($tasks);
    $complete = 0;
    $started  = 0;
    foreach ($tasks as $t) {
        $p = $progressMap[$t->id] ?? null;
        if ($p && $p->status === 'complete') {
            $complete++;
        } elseif ($p && $p->status === 'inprogress') {
            $started++;
        }
    }
    if ($complete === $total) {
        return 'complete';
    }
    if ($complete > 0 || $started > 0) {
        return 'inprogress';
    }
    return 'notstarted';
}

/**
 * Colour CSS class map.
 */
function local_trainingpathways_colour_class(string $colour): string {
    $map = [
        'blue'   => '#1565c0',
        'green'  => '#2e7d32',
        'purple' => '#6a1b9a',
        'orange' => '#e65100',
        'teal'   => '#00695c',
        'red'    => '#c62828',
        'grey'   => '#546e7a',
    ];
    return $map[$colour] ?? $map['blue'];
}

/**
 * Task type icon HTML (text-based, no image dependencies).
 */
function local_trainingpathways_task_icon(string $tasktype): string {
    $icons = [
        'course'   => '&#128218;',
        'quiz'     => '&#128203;',
        'video'    => '&#127909;',
        'document' => '&#128196;',
        'signoff'  => '&#9997;',
        'task'     => '&#9745;',
    ];
    return $icons[$tasktype] ?? '&#9745;';
}

/**
 * Render the standard plugin header/nav tabs.
 */
function local_trainingpathways_render_header(string $activetab = 'view'): void {
    global $OUTPUT;
    $context = context_system::instance();
    $canManage  = has_capability('local/trainingpathways:manage',  $context);
    $canViewAll = has_capability('local/trainingpathways:viewall', $context);

    echo '<div class="tp-nav-tabs mb-4" style="border-bottom:2px solid #dee2e6;display:flex;gap:0;flex-wrap:wrap">';

    $tabs = [];
    if (has_capability('local/trainingpathways:view', $context)) {
        $tabs[] = ['key'=>'view',      'url'=>'view.php',      'label'=>'&#127775; My Pathway'];
    }
    if ($canViewAll || $canManage) {
        $tabs[] = ['key'=>'dashboard', 'url'=>'dashboard.php', 'label'=>'&#128202; Team Dashboard'];
    }
    if ($canManage) {
        $tabs[] = ['key'=>'manage',    'url'=>'manage.php',    'label'=>'&#9881; Manage Pathways'];
        $tabs[] = ['key'=>'assign',    'url'=>'assign.php',    'label'=>'&#128100; Assign'];
    }

    foreach ($tabs as $tab) {
        $active = ($tab['key'] === $activetab);
        $style  = $active
            ? 'background:#fff;border:2px solid #dee2e6;border-bottom-color:#fff;margin-bottom:-2px;font-weight:600;color:#1565c0'
            : 'background:transparent;border:2px solid transparent;color:#495057';
        $url = new moodle_url('/local/trainingpathways/' . $tab['url']);
        echo '<a href="' . $url->out() . '" style="padding:0.6rem 1.2rem;text-decoration:none;font-size:0.9rem;'
            . 'border-radius:4px 4px 0 0;white-space:nowrap;' . $style . '">'
            . $tab['label'] . '</a>';
    }
    echo '</div>';
}
