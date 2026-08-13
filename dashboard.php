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
 * local_trainingpathways file.
 *
 * @package    local_trainingpathways
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/trainingpathways:viewall', $context);

$filterPathway = optional_param('filter_pathway', 0, PARAM_INT);
$filterStatus  = optional_param('filter_status',  'active', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/trainingpathways/dashboard.php'));
$PAGE->set_title(get_string('page_dashboard', 'local_trainingpathways'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/local/trainingpathways/styles.css');

// ── Load assignments ───────────────────────────────────────────────────────
$params = [];
$where  = '1=1';

if ($filterPathway) {
    $where .= ' AND a.pathwayid = :fpid';
    $params['fpid'] = $filterPathway;
}
if ($filterStatus === 'completed') {
    $where .= " AND a.status = 'completed'";
} else {
    $where .= " AND a.status = 'active'";
}

$assignments = $DB->get_records_sql(
    "SELECT a.*, p.name AS pathwayname, p.colour AS pathwaycolour,
            " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS fullname,
            u.email, u.picture, u.imagealt
       FROM {local_tp_assignments} a
       JOIN {local_tp_pathways} p ON p.id = a.pathwayid
       JOIN {user} u              ON u.id = a.userid
      WHERE $where
      ORDER BY u.lastname ASC, u.firstname ASC",
    $params
);

// Summary stats (always from active assignments).
$totalActive = (int)$DB->count_records('local_tp_assignments', ['status' => 'active']);
$totalComplete = (int)$DB->count_records('local_tp_assignments', ['status' => 'completed']);
$overdueCount = (int)$DB->count_records_sql(
    "SELECT COUNT(id) FROM {local_tp_assignments}
      WHERE status = 'active' AND targetdate IS NOT NULL AND targetdate < :now",
    ['now' => time()]);
$inProgress = (int)$DB->count_records_sql(
    "SELECT COUNT(DISTINCT a.id)
       FROM {local_tp_assignments} a
       JOIN {local_tp_progress} p ON p.assignmentid = a.id AND p.status = 'complete'
      WHERE a.status = 'active'");

$pathways = $DB->get_records('local_tp_pathways', ['status' => 'active'], 'name ASC');

// Build progress for each assignment.
$progData = [];
foreach ($assignments as $a) {
    $progData[$a->id] = local_trainingpathways_assignment_progress($a->id);
}

echo $OUTPUT->header();
local_trainingpathways_render_header('dashboard');
?>

<div class="container-fluid" style="max-width:1200px">

<div class="tp-page-title"><?php echo get_string('page_dashboard','local_trainingpathways'); ?></div>
<div class="tp-page-subtitle"><?php echo get_string('dashboard_subtitle','local_trainingpathways'); ?></div>

<!-- Help box: reading the dashboard -->
<details class="tp-help-box">
  <summary>&#9432; How to read this dashboard</summary>
  <div class="tp-help-box-body">
    <p class="tp-help-intro">The Team Training Dashboard gives you a real-time snapshot of every active and completed pathway assignment across your organisation. Use it to track progress, spot employees who are falling behind, and identify training bottlenecks.</p>
    <div class="tp-help-section-label">What the stat cards mean</div>
    <div class="tp-help-grid" style="margin-bottom:0.75rem">
      <div class="tp-help-grid-item"><strong>&#128100; Assigned Employees</strong>Total number of currently active pathway assignments (one employee may appear multiple times if assigned to multiple pathways).</div>
      <div class="tp-help-grid-item"><strong>&#10003; Completed</strong>Assignments where the employee has completed all required tasks.</div>
      <div class="tp-help-grid-item"><strong>&#9654; In Progress</strong>Active assignments where the employee has completed at least one task.</div>
      <div class="tp-help-grid-item"><strong>&#9888; Overdue</strong>Active assignments where the target completion date has passed but the pathway is not yet complete.</div>
    </div>
    <ul class="tp-help-steps">
      <li class="tp-help-step"><span class="tp-help-step-num">&#9670;</span><span><strong>Filter by pathway</strong> using the dropdown on the left to focus on a single training program.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">&#9670;</span><span><strong>Switch between Active and Completed</strong> views using the toggle buttons.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">&#9670;</span><span><strong>Click View</strong> on any row to open that employee's detailed stage-by-stage progress page, where managers can also sign off on approval tasks.</span></li>
    </ul>
  </div>
</details>

<!-- Stats cards -->
<div class="tp-stat-cards">
  <div class="tp-stat-card">
    <div class="tp-stat-value" style="color:#1565c0"><?php echo $totalActive; ?></div>
    <div class="tp-stat-label"><?php echo get_string('stat_total','local_trainingpathways'); ?></div>
  </div>
  <div class="tp-stat-card">
    <div class="tp-stat-value" style="color:#2e7d32"><?php echo $totalComplete; ?></div>
    <div class="tp-stat-label"><?php echo get_string('stat_complete','local_trainingpathways'); ?></div>
  </div>
  <div class="tp-stat-card">
    <div class="tp-stat-value" style="color:#f9a825"><?php echo $inProgress; ?></div>
    <div class="tp-stat-label"><?php echo get_string('stat_inprogress','local_trainingpathways'); ?></div>
  </div>
  <div class="tp-stat-card">
    <div class="tp-stat-value" style="color:#c62828"><?php echo $overdueCount; ?></div>
    <div class="tp-stat-label"><?php echo get_string('stat_overdue','local_trainingpathways'); ?></div>
  </div>
</div>

<!-- Filters -->
<div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem">
  <select onchange="location.href=tpFilterUrl({pathway:this.value})"
          style="border:1px solid #ced4da;border-radius:4px;padding:0.3rem 0.6rem;font-size:0.88rem">
    <option value="0" <?php echo !$filterPathway?'selected':''; ?>><?php echo get_string('filter_all','local_trainingpathways'); ?></option>
    <?php foreach ($pathways as $pw): ?>
      <option value="<?php echo $pw->id; ?>" <?php echo ($filterPathway==$pw->id)?'selected':''; ?>>
        <?php echo s($pw->name); ?>
      </option>
    <?php endforeach; ?>
  </select>

  <div style="display:flex;border:1px solid #ced4da;border-radius:4px;overflow:hidden;font-size:0.85rem">
    <a href="?filter_pathway=<?php echo $filterPathway; ?>&filter_status=active"
       style="padding:0.3rem 0.75rem;text-decoration:none;
              <?php echo $filterStatus!=='completed'?'background:#1565c0;color:#fff':'background:#fff;color:#495057'; ?>">
      Active
    </a>
    <a href="?filter_pathway=<?php echo $filterPathway; ?>&filter_status=completed"
       style="padding:0.3rem 0.75rem;text-decoration:none;border-left:1px solid #ced4da;
              <?php echo $filterStatus==='completed'?'background:#1565c0;color:#fff':'background:#fff;color:#495057'; ?>">
      Completed
    </a>
  </div>

  <?php if (has_capability('local/trainingpathways:assign', $context)): ?>
    <a href="<?php echo (new moodle_url('/local/trainingpathways/assign.php'))->out(); ?>"
       class="btn btn-sm btn-outline-primary" style="margin-left:auto">
      &#128100; Assign Pathway
    </a>
  <?php endif; ?>
</div>

<!-- Team table -->
<?php if (empty($assignments)): ?>
  <div class="tp-empty-state"><div class="icon">&#128100;</div><p><?php echo get_string('no_assignments','local_trainingpathways'); ?></p></div>
<?php else: ?>
<div style="overflow-x:auto">
<table class="tp-team-table">
  <thead>
    <tr>
      <th><?php echo get_string('col_employee','local_trainingpathways'); ?></th>
      <th><?php echo get_string('col_pathway','local_trainingpathways'); ?></th>
      <th style="width:160px"><?php echo get_string('col_progress','local_trainingpathways'); ?></th>
      <th><?php echo get_string('col_stage','local_trainingpathways'); ?></th>
      <th><?php echo get_string('col_assigned','local_trainingpathways'); ?></th>
      <th><?php echo get_string('col_target','local_trainingpathways'); ?></th>
      <th style="width:80px"></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($assignments as $a):
        $prog   = $progData[$a->id];
        $colour = local_trainingpathways_colour_class($a->pathwaycolour);
        $isOverdue = $a->targetdate && $a->targetdate < time() && $prog['percent'] < 100;
        $targetStr = $a->targetdate ? userdate($a->targetdate, '%d %b %Y') : '—';

        // Current stage = first in-progress or next not-started stage.
        $stages    = local_trainingpathways_get_pathway_structure($a->pathwayid);
        $pMap      = local_trainingpathways_get_progress_map($a->id);
        $curStage  = '';
        foreach ($stages as $s) {
            $taskArr = array_values((array)$s->tasks);
            $ss = local_trainingpathways_stage_status($taskArr, $pMap);
            if ($ss === 'inprogress') { $curStage = $s->name; break; }
            if ($ss === 'notstarted' && $curStage === '') { $curStage = $s->name; }
        }
        if ($prog['percent'] >= 100) $curStage = 'Complete &#10003;';
    ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:0.5rem">
          <?php
          $userpicture = new user_picture($DB->get_record('user',['id'=>$a->userid],'id,picture,firstname,lastname,imagealt'));
          $userpicture->size = 32;
          echo $OUTPUT->render($userpicture);
          ?>
          <div>
            <strong style="font-size:0.9rem"><?php echo s($a->fullname); ?></strong><br>
            <small style="color:#6c757d"><?php echo s($a->email); ?></small>
          </div>
        </div>
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.88rem">
          <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $colour; ?>;flex-shrink:0"></span>
          <?php echo s($a->pathwayname); ?>
        </span>
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:0.4rem">
          <div class="tp-mini-bar-wrap" style="flex:1">
            <div class="tp-mini-bar" style="width:<?php echo $prog['percent']; ?>%;background:<?php echo $colour; ?>"></div>
          </div>
          <small style="white-space:nowrap;font-weight:600;color:<?php echo $colour; ?>"><?php echo $prog['percent']; ?>%</small>
        </div>
        <small style="color:#6c757d;font-size:0.75rem"><?php echo $prog['complete']; ?>/<?php echo $prog['total']; ?> tasks</small>
      </td>
      <td style="font-size:0.85rem;color:#495057"><?php echo $curStage ? s($curStage) : '—'; ?></td>
      <td><small><?php echo userdate($a->timeassigned, '%d %b %Y'); ?></small></td>
      <td>
        <?php if ($isOverdue): ?>
          <span style="color:#c62828;font-size:0.82rem;white-space:nowrap">&#9888; <?php echo $targetStr; ?></span>
        <?php else: ?>
          <small><?php echo $targetStr; ?></small>
        <?php endif; ?>
      </td>
      <td>
        <a href="<?php echo (new moodle_url('/local/trainingpathways/view.php',['userid'=>$a->userid,'assignmentid'=>$a->id]))->out(); ?>"
           class="tp-btn-sm">View</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

</div>

<script>
function tpFilterUrl(overrides) {
    var params = {
        filter_pathway: <?php echo $filterPathway; ?>,
        filter_status:  '<?php echo s($filterStatus); ?>'
    };
    for (var k in overrides) { params[k] = overrides[k]; }
    return '<?php echo (new moodle_url('/local/trainingpathways/dashboard.php'))->out(); ?>'
        + '?filter_pathway=' + params.filter_pathway
        + '&filter_status=' + params.filter_status;
}
</script>

<?php echo $OUTPUT->footer(); ?>
