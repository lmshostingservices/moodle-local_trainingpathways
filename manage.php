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
require_capability('local/trainingpathways:manage', $context);

$action     = optional_param('action',     '',  PARAM_ALPHA);
$pathwayid  = optional_param('pathwayid',  0,   PARAM_INT);
$showarchive= optional_param('archived',   0,   PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/trainingpathways/manage.php'));
$PAGE->set_title(get_string('page_manage', 'local_trainingpathways'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/local/trainingpathways/styles.css');

// ── Actions ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $pathwayid) {
    require_sesskey();
    if ($DB->record_exists('local_tp_assignments', ['pathwayid' => $pathwayid])) {
        throw new moodle_exception('allocatedpathwaylocked', 'local_trainingpathways');
    }
    $DB->delete_records('local_tp_progress',    ['assignmentid' => $DB->get_field('local_tp_assignments', 'id', ['pathwayid'=>$pathwayid])]);
    $DB->delete_records('local_tp_assignments', ['pathwayid' => $pathwayid]);
    // Delete tasks for all stages
    $stages = $DB->get_records('local_tp_stages', ['pathwayid' => $pathwayid]);
    foreach ($stages as $s) {
        $DB->delete_records('local_tp_tasks', ['stageid' => $s->id]);
    }
    $DB->delete_records('local_tp_stages',   ['pathwayid' => $pathwayid]);
    $DB->delete_records('local_tp_pathways', ['id'        => $pathwayid]);
    redirect(new moodle_url('/local/trainingpathways/manage.php'),
        get_string('pathway_deleted', 'local_trainingpathways'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'archive' && $pathwayid) {
    require_sesskey();
    $DB->set_field('local_tp_pathways', 'status', 'archived', ['id' => $pathwayid]);
    redirect(new moodle_url('/local/trainingpathways/manage.php'),
        get_string('pathway_archived', 'local_trainingpathways'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'unarchive' && $pathwayid) {
    require_sesskey();
    $DB->set_field('local_tp_pathways', 'status', 'active', ['id' => $pathwayid]);
    redirect(new moodle_url('/local/trainingpathways/manage.php', ['archived'=>1]),
        get_string('pathway_unarchived', 'local_trainingpathways'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Load pathways ──────────────────────────────────────────────────────────
$statusFilter = $showarchive ? 'archived' : 'active';
$pathways = $DB->get_records('local_tp_pathways', ['status' => $statusFilter], 'sortorder ASC, name ASC');

foreach ($pathways as &$pw) {
    $stageCount = $DB->count_records('local_tp_stages', ['pathwayid' => $pw->id]);
    $taskCount  = (int)$DB->count_records_sql(
        "SELECT COUNT(t.id) FROM {local_tp_tasks} t
           JOIN {local_tp_stages} s ON s.id = t.stageid WHERE s.pathwayid = :pid",
        ['pid' => $pw->id]);
    $assignCount = $DB->count_records('local_tp_assignments', ['pathwayid' => $pw->id, 'status' => 'active']);
    $pw->stagecount  = $stageCount;
    $pw->taskcount   = $taskCount;
    $pw->assigncount = $assignCount;
}
unset($pw);

echo $OUTPUT->header();
local_trainingpathways_render_header('manage');
?>

<div class="container-fluid" style="max-width:1100px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem">
    <div>
      <div class="tp-page-title"><?php echo get_string('page_manage', 'local_trainingpathways'); ?></div>
      <div class="tp-page-subtitle"><?php echo get_string('manage_subtitle', 'local_trainingpathways'); ?></div>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
      <?php if ($showarchive): ?>
        <a href="<?php echo (new moodle_url('/local/trainingpathways/manage.php'))->out(); ?>"
           class="btn btn-outline-secondary btn-sm">&#9668; Active Pathways</a>
      <?php else: ?>
        <a href="<?php echo (new moodle_url('/local/trainingpathways/manage.php', ['archived'=>1]))->out(); ?>"
           class="btn btn-outline-secondary btn-sm">Archived</a>
      <?php endif; ?>
      <?php if (!$showarchive): ?>
        <a href="<?php echo (new moodle_url('/local/trainingpathways/pathway_edit.php'))->out(); ?>"
           class="btn btn-primary btn-sm">&#43; <?php echo get_string('create_pathway','local_trainingpathways'); ?></a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Help box: how training pathways work -->
  <details class="tp-help-box" open>
    <summary>&#9432; How Training Pathways work</summary>
    <div class="tp-help-box-body">
      <p class="tp-help-intro">Training Pathways let you create structured, step-by-step training programs and assign them to your team. Each pathway is made up of <strong>Stages</strong> (phases or milestones) containing <strong>Tasks</strong> (individual learning actions).</p>
      <ul class="tp-help-steps">
        <li class="tp-help-step"><span class="tp-help-step-num">1</span><span><strong>Create a pathway</strong> — click <em>Create New Pathway</em> above. Give it a name, description, colour, and build out your stages and tasks.</span></li>
        <li class="tp-help-step"><span class="tp-help-step-num">2</span><span><strong>Assign it to employees</strong> — click the <em>Assign</em> button on any pathway card below. You can assign to multiple employees at once and optionally set a target completion date.</span></li>
        <li class="tp-help-step"><span class="tp-help-step-num">3</span><span><strong>Monitor progress</strong> — use the <em>Team Dashboard</em> (in the top navigation) to see each employee's stage-by-stage progress, spot overdue assignments, and view detailed breakdowns.</span></li>
        <li class="tp-help-step"><span class="tp-help-step-num">4</span><span><strong>Edit or archive</strong> — use the action buttons on each card to edit a pathway's content, archive ones no longer in use, or delete them entirely.</span></li>
      </ul>
      <div class="tp-help-tip"><span class="tp-help-tip-icon">&#128161;</span><span><strong>Tip:</strong> Archive pathways you are no longer assigning to keep this page tidy. Archived pathways and all their assignment history are preserved — you can restore them at any time by clicking <em>Archived</em>.</span></div>
    </div>
  </details>

  <?php if (empty($pathways)): ?>
    <div class="tp-empty-state">
      <div class="icon">&#128218;</div>
      <?php if ($showarchive): ?>
        <p>No archived pathways.</p>
      <?php else: ?>
        <p><?php echo get_string('no_pathways', 'local_trainingpathways'); ?></p>
        <a href="<?php echo (new moodle_url('/local/trainingpathways/pathway_edit.php'))->out(); ?>"
           class="btn btn-primary">&#43; Create Your First Pathway</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tp-pathway-grid">
      <?php foreach ($pathways as $pw):
          $colour    = local_trainingpathways_colour_class($pw->colour);
          $editUrl   = new moodle_url('/local/trainingpathways/pathway_edit.php', ['id' => $pw->id]);
          $assignUrl = new moodle_url('/local/trainingpathways/assign.php', ['pathwayid' => $pw->id]);
          $archiveUrl = new moodle_url('/local/trainingpathways/manage.php', [
              'action'    => $showarchive ? 'unarchive' : 'archive',
              'pathwayid' => $pw->id,
              'sesskey'   => sesskey(),
          ]);
          $deleteUrl = new moodle_url('/local/trainingpathways/manage.php', [
              'action'    => 'delete',
              'pathwayid' => $pw->id,
              'sesskey'   => sesskey(),
          ]);
      ?>
      <div class="tp-pathway-card">
        <div class="tp-pathway-card-stripe" style="background:<?php echo $colour; ?>"></div>
        <div class="tp-pathway-card-body">
          <div class="tp-pathway-card-name">
            <?php echo s($pw->name); ?>
            <?php if ($showarchive): ?>
              <span class="tp-archived-badge">Archived</span>
            <?php endif; ?>
          </div>
          <?php if ($pw->description): ?>
            <div class="tp-pathway-card-desc"><?php echo s($pw->description); ?></div>
          <?php endif; ?>
          <div class="tp-pathway-card-stats">
            <span><strong><?php echo $pw->stagecount; ?></strong> stages</span>
            <span><strong><?php echo $pw->taskcount; ?></strong> tasks</span>
            <span><strong><?php echo $pw->assigncount; ?></strong> active assignments</span>
          </div>
        </div>
        <div class="tp-pathway-card-actions">
          <?php if (!$showarchive): ?>
            <a href="<?php echo $editUrl->out(); ?>" class="tp-btn-sm primary">&#9998; Edit</a>
            <a href="<?php echo $assignUrl->out(); ?>" class="tp-btn-sm">&#128100; Assign</a>
            <a href="<?php echo $archiveUrl->out(); ?>" class="tp-btn-sm"
               onclick="return confirm('Archive this pathway?')">&#128230; Archive</a>
          <?php else: ?>
            <a href="<?php echo $archiveUrl->out(); ?>" class="tp-btn-sm primary">&#8635; Restore</a>
          <?php endif; ?>
          <a href="<?php echo $deleteUrl->out(); ?>" class="tp-btn-sm danger"
             onclick="return confirm('<?php echo get_string('confirm_delete_pathway','local_trainingpathways'); ?>')">
            &#128465; Delete</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php echo $OUTPUT->footer(); ?>
