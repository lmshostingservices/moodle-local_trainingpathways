<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/trainingpathways:view', $context);

// Managers/admins can view any user's pathway.
$viewUserId   = optional_param('userid',       $USER->id, PARAM_INT);
$assignmentid = optional_param('assignmentid', 0,         PARAM_INT);

// Non-managers can only view their own pathway.
if ($viewUserId !== (int)$USER->id && !has_capability('local/trainingpathways:viewall', $context)) {
    $viewUserId = (int)$USER->id;
}

$viewUser = $DB->get_record('user', ['id' => $viewUserId], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/trainingpathways/view.php',
    $viewUserId !== (int)$USER->id ? ['userid' => $viewUserId] : []));
$PAGE->set_title(get_string('page_my_pathway', 'local_trainingpathways'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/local/trainingpathways/styles.css');

// ── Handle AJAX-style task-toggle (non-JS fallback POST) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $postAid  = required_param('assignmentid', PARAM_INT);
    $postTid  = required_param('taskid',       PARAM_INT);
    $newStatus= required_param('status',       PARAM_ALPHA);
    $asgn     = $DB->get_record('local_tp_assignments', ['id'=>$postAid,'userid'=>$viewUserId], '*', MUST_EXIST);

    $existing = $DB->get_record('local_tp_progress', ['assignmentid'=>$postAid,'taskid'=>$postTid]);
    $now = time();
    if ($existing) {
        $DB->update_record('local_tp_progress', (object)[
            'id'           => $existing->id,
            'status'       => $newStatus,
            'timemodified' => $now,
        ]);
    } else {
        $DB->insert_record('local_tp_progress', (object)[
            'assignmentid' => $postAid,
            'taskid'       => $postTid,
            'userid'       => $viewUserId,
            'status'       => $newStatus,
            'timemodified' => $now,
        ]);
    }
    // Check if all required tasks now complete → auto-complete assignment.
    $prog = local_trainingpathways_assignment_progress($postAid);
    if ($prog['percent'] >= 100) {
        $DB->set_field('local_tp_assignments', 'status', 'completed', ['id'=>$postAid]);
    }
    redirect(new moodle_url('/local/trainingpathways/view.php',
        $viewUserId !== (int)$USER->id ? ['userid'=>$viewUserId,'assignmentid'=>$postAid] : []),
        get_string('task_updated','local_trainingpathways'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Load assignments for this user ─────────────────────────────────────────
$allAssignments = $DB->get_records_sql(
    "SELECT a.*, p.name AS pathwayname, p.colour AS pathwaycolour, p.description AS pathwaydesc
       FROM {local_tp_assignments} a
       JOIN {local_tp_pathways} p ON p.id = a.pathwayid
      WHERE a.userid = :uid AND a.status IN ('active','completed')
      ORDER BY a.timeassigned DESC",
    ['uid' => $viewUserId]
);

// Pick active assignment.
$currentAssignment = null;
if ($assignmentid) {
    $currentAssignment = $allAssignments[$assignmentid] ?? null;
}
if (!$currentAssignment) {
    foreach ($allAssignments as $a) {
        if ($a->status === 'active') { $currentAssignment = $a; break; }
    }
}
if (!$currentAssignment && !empty($allAssignments)) {
    $currentAssignment = reset($allAssignments);
}

echo $OUTPUT->header();
local_trainingpathways_render_header('view');

if ((int)$viewUserId !== (int)$USER->id) {
    echo '<div class="alert alert-info" style="font-size:0.9rem">&#128202; Viewing pathway for: '
        . '<strong>' . fullname($viewUser) . '</strong>'
        . ' &mdash; <a href="' . (new moodle_url('/local/trainingpathways/dashboard.php'))->out() . '">Back to dashboard</a></div>';
}
?>

<div class="container-fluid" style="max-width:800px">

<?php if (empty($allAssignments)): ?>
  <div class="tp-empty-state">
    <div class="icon">&#127775;</div>
    <p><?php echo get_string('no_pathway_assigned','local_trainingpathways'); ?></p>
  </div>

<?php else:
    // Multi-pathway switcher (if more than one).
    if (count($allAssignments) > 1): ?>
      <div style="margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
        <span style="font-size:0.88rem;color:#6c757d">Pathway:</span>
        <?php foreach ($allAssignments as $a):
            $isActive = ($currentAssignment && $a->id === $currentAssignment->id);
            $colour   = local_trainingpathways_colour_class($a->pathwaycolour);
            $switchUrl= new moodle_url('/local/trainingpathways/view.php', array_filter([
                'userid'       => ($viewUserId !== (int)$USER->id) ? $viewUserId : 0,
                'assignmentid' => $a->id,
            ]));
        ?>
          <a href="<?php echo $switchUrl->out(); ?>"
             style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.3rem 0.75rem;
                    border-radius:999px;text-decoration:none;font-size:0.85rem;
                    <?php echo $isActive ? "background:$colour;color:#fff;font-weight:600" : 'background:#f1f3f5;color:#495057'; ?>">
            <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $isActive ? '#fff' : $colour; ?>;flex-shrink:0"></span>
            <?php echo s($a->pathwayname); ?>
            <?php if ($a->status === 'completed'): ?> &#10003;<?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif;

    if (!$currentAssignment): ?>
      <div class="alert alert-warning">No pathway found.</div>
    <?php else:
        $stages      = local_trainingpathways_get_pathway_structure($currentAssignment->pathwayid);
        $progressMap = local_trainingpathways_get_progress_map($currentAssignment->id);
        $prog        = local_trainingpathways_assignment_progress($currentAssignment->id);
        $colour      = local_trainingpathways_colour_class($currentAssignment->pathwaycolour);
        $canSignoff  = has_capability('local/trainingpathways:signoff', $context);

        // Target date info
        $targetInfo = '';
        if ($currentAssignment->targetdate) {
            $diff = (int)(($currentAssignment->targetdate - time()) / 86400);
            if ($diff < 0) {
                $targetInfo = '<span style="color:#c62828">&#9888; ' . abs($diff) . ' days overdue</span>';
            } else {
                $targetInfo = '<span style="color:#6c757d">' . $diff . ' days remaining</span>';
            }
        }
    ?>

    <!-- Pathway header -->
    <div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;margin-bottom:1.5rem">
      <div style="height:6px;background:<?php echo $colour; ?>"></div>
      <div style="padding:1.25rem">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
          <div>
            <h1 style="font-size:1.3rem;font-weight:700;margin:0 0 0.2rem;color:#212529">
              <?php echo s($currentAssignment->pathwayname); ?>
            </h1>
            <?php if ($currentAssignment->pathwaydesc): ?>
              <p style="color:#6c757d;font-size:0.88rem;margin:0"><?php echo s($currentAssignment->pathwaydesc); ?></p>
            <?php endif; ?>
          </div>
          <div style="text-align:right;font-size:0.82rem;color:#6c757d">
            <div>Assigned: <?php echo userdate($currentAssignment->timeassigned, '%d %b %Y'); ?></div>
            <?php if ($targetInfo): ?><div><?php echo $targetInfo; ?></div><?php endif; ?>
          </div>
        </div>

        <!-- Overall progress -->
        <div style="display:flex;align-items:center;gap:0.75rem">
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem">
              <span style="font-size:0.82rem;font-weight:600;color:#495057">
                <?php echo get_string('overall_progress','local_trainingpathways'); ?>
              </span>
              <span style="font-size:0.82rem;color:#6c757d">
                <?php echo $prog['complete']; ?>/<?php echo $prog['total']; ?> tasks
              </span>
            </div>
            <div class="tp-progress-wrap">
              <div class="tp-progress-bar" style="width:<?php echo $prog['percent']; ?>%;background:<?php echo $colour; ?>"></div>
            </div>
          </div>
          <div style="font-size:1.4rem;font-weight:700;color:<?php echo $colour; ?>;min-width:3rem;text-align:right">
            <?php echo $prog['percent']; ?>%
          </div>
        </div>

        <?php if ($currentAssignment->status === 'completed'): ?>
          <div style="margin-top:0.75rem;background:#e8f5e9;border-radius:4px;padding:0.5rem 0.75rem;font-size:0.88rem;color:#1b5e20;font-weight:600">
            &#10003; Pathway Complete — well done!
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Help box: only shown when viewing own pathway -->
    <?php if ($viewUserId === (int)$USER->id): ?>
    <details class="tp-help-box">
      <summary>&#9432; How to use your training pathway</summary>
      <div class="tp-help-box-body">
        <p class="tp-help-intro">Your training pathway is a structured learning journey set up by your manager. It is divided into <strong>Stages</strong> (phases or milestones) which each contain <strong>Tasks</strong> — individual actions for you to complete. Work through each stage in order to build your skills and track your progress.</p>
        <ul class="tp-help-steps">
          <li class="tp-help-step"><span class="tp-help-step-num">1</span><span><strong>Click on a stage</strong> in the timeline below to expand it and see the tasks inside.</span></li>
          <li class="tp-help-step"><span class="tp-help-step-num">2</span><span><strong>Complete tasks</strong> as you work through them — click <em>Mark Complete</em> on each task once done. Your overall progress bar updates automatically.</span></li>
          <li class="tp-help-step"><span class="tp-help-step-num">3</span><span><strong>Open linked resources</strong> — tasks with a course, video, or document attached will show an <em>Open Course</em> or <em>Open Resource</em> button. Click it to access the content directly.</span></li>
          <li class="tp-help-step"><span class="tp-help-step-num">4</span><span><strong>Sign-Off tasks</strong> require your manager to approve. When you are ready, let your manager know — they can sign it off from their view of your pathway. You will see it update to <em>Signed Off</em> once approved.</span></li>
        </ul>
        <div class="tp-help-tip"><span class="tp-help-tip-icon">&#128161;</span><span><strong>Required vs. Optional:</strong> Tasks labelled <em>Required</em> must be completed to finish the pathway and reach 100%. <em>Optional</em> tasks are shown for your benefit but do not affect your completion percentage.</span></div>
      </div>
    </details>
    <?php endif; ?>

    <!-- Stage timeline -->
    <?php if (empty($stages)): ?>
      <div class="tp-empty-state"><p>This pathway has no stages configured yet.</p></div>
    <?php else: ?>
      <ul class="tp-timeline">
        <?php foreach ($stages as $stageIdx => $stage):
            $taskArr    = array_values((array)$stage->tasks);
            $stageStatus= local_trainingpathways_stage_status($taskArr, $progressMap);
            $isLast     = ($stageIdx === count($stages) - 1);
            $domId      = 'tp-stage-' . $stage->id;
            $taskCount  = count($taskArr);
            $doneCount  = 0;
            foreach ($taskArr as $t) {
                if (isset($progressMap[$t->id]) && $progressMap[$t->id]->status === 'complete') $doneCount++;
            }

            $iconHtml = match($stageStatus) {
                'complete'   => '&#10003;',
                'inprogress' => '&#9654;',
                default      => (string)($stageIdx + 1),
            };
            $badgeClass = 'tp-badge-' . $stageStatus;
            $badgeLabel = match($stageStatus) {
                'complete'   => get_string('stage_complete',   'local_trainingpathways'),
                'inprogress' => get_string('stage_inprogress', 'local_trainingpathways'),
                default      => get_string('stage_notstarted', 'local_trainingpathways'),
            };
        ?>
        <li class="tp-stage">
          <?php if (!$isLast): ?><div class="tp-stage-connector"></div><?php endif; ?>

          <div class="tp-stage-header" onclick="tpToggleStage('<?php echo $domId; ?>')">
            <div class="tp-stage-icon <?php echo $stageStatus; ?>"><?php echo $iconHtml; ?></div>
            <div class="tp-stage-body">
              <div class="tp-stage-name"><?php echo s($stage->name); ?></div>
              <?php if ($stage->description): ?>
                <div class="tp-stage-meta"><?php echo s($stage->description); ?></div>
              <?php endif; ?>
              <div class="tp-stage-meta"><?php echo $doneCount; ?>/<?php echo $taskCount; ?> tasks complete</div>
            </div>
            <span class="tp-stage-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
            <span id="<?php echo $domId; ?>-arrow" style="color:#6c757d;font-size:0.8rem;flex-shrink:0">&#9654;</span>
          </div>

          <?php if (!empty($taskArr)): ?>
          <ul class="tp-tasks" id="<?php echo $domId; ?>-tasks">
            <?php foreach ($taskArr as $task):
                $pRec   = $progressMap[$task->id] ?? null;
                $tStatus= $pRec ? $pRec->status : 'notstarted';
                $isDone = ($tStatus === 'complete');
                $isSignoff = ($task->tasktype === 'signoff');
                $icon   = local_trainingpathways_task_icon($task->tasktype);
                $viewUrl = ($task->tasktype === 'course' || $task->tasktype === 'quiz') && $task->courseid
                    ? new moodle_url('/course/view.php', ['id' => $task->courseid]) : null;
                $resUrl = ($task->resourceurl && in_array($task->tasktype, ['video','document']))
                    ? $task->resourceurl : null;
                $actionUrl = new moodle_url('/local/trainingpathways/view.php',
                    array_filter(['userid' => ($viewUserId !== (int)$USER->id) ? $viewUserId : 0]));
            ?>
            <li class="tp-task-row">
              <span class="tp-task-icon"><?php echo $icon; ?></span>
              <div style="flex:1;min-width:0">
                <div class="tp-task-name <?php echo $isDone ? 'complete' : ''; ?>">
                  <?php echo s($task->name); ?>
                </div>
                <?php if ($task->description): ?>
                  <div class="tp-task-desc"><?php echo s($task->description); ?></div>
                <?php endif; ?>
              </div>

              <?php if ($task->required): ?>
                <span class="tp-task-badge-required">Required</span>
              <?php else: ?>
                <span class="tp-task-badge-optional">Optional</span>
              <?php endif; ?>

              <div class="tp-task-actions">
                <?php if ($viewUrl): ?>
                  <a href="<?php echo $viewUrl->out(); ?>" class="tp-btn-sm primary" target="_blank">
                    <?php echo get_string('task_open_course','local_trainingpathways'); ?>
                  </a>
                <?php endif; ?>

                <?php if ($resUrl): ?>
                  <a href="<?php echo s($resUrl); ?>" class="tp-btn-sm primary" target="_blank">
                    <?php echo get_string('task_open_resource','local_trainingpathways'); ?>
                  </a>
                <?php endif; ?>

                <?php if ($isSignoff): ?>
                  <?php if ($isDone): ?>
                    <span class="tp-btn-sm success" style="cursor:default">&#10003; <?php echo get_string('task_signed_off','local_trainingpathways'); ?></span>
                  <?php elseif ($canSignoff && $viewUserId !== (int)$USER->id): ?>
                    <!-- Manager can sign off inline -->
                    <form method="post" action="<?php echo $actionUrl->out(); ?>" style="display:inline">
                      <input type="hidden" name="sesskey"      value="<?php echo sesskey(); ?>">
                      <input type="hidden" name="assignmentid" value="<?php echo $currentAssignment->id; ?>">
                      <input type="hidden" name="taskid"       value="<?php echo $task->id; ?>">
                      <input type="hidden" name="status"       value="complete">
                      <button type="submit" class="tp-btn-sm success">&#9997; Sign Off</button>
                    </form>
                  <?php else: ?>
                    <span class="tp-btn-sm" style="cursor:default;color:#6c757d">
                      <?php echo get_string('task_awaiting_signoff','local_trainingpathways'); ?>
                    </span>
                  <?php endif; ?>

                <?php elseif ($viewUserId === (int)$USER->id || $canSignoff): ?>
                  <form method="post" action="<?php echo $actionUrl->out(); ?>" style="display:inline">
                    <input type="hidden" name="sesskey"      value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="assignmentid" value="<?php echo $currentAssignment->id; ?>">
                    <input type="hidden" name="taskid"       value="<?php echo $task->id; ?>">
                    <input type="hidden" name="status"       value="<?php echo $isDone ? 'notstarted' : 'complete'; ?>">
                    <button type="submit" class="tp-btn-sm <?php echo $isDone ? '' : 'success'; ?>">
                      <?php echo $isDone
                          ? get_string('task_mark_incomplete','local_trainingpathways')
                          : get_string('task_mark_complete','local_trainingpathways'); ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

</div>

<script>
function tpToggleStage(domId) {
    var tasks = document.getElementById(domId + '-tasks');
    var arrow = document.getElementById(domId + '-arrow');
    if (!tasks) return;
    var isOpen = tasks.classList.contains('open');
    if (isOpen) {
        tasks.classList.remove('open');
        if (arrow) arrow.innerHTML = '&#9654;';
    } else {
        tasks.classList.add('open');
        if (arrow) arrow.innerHTML = '&#9660;';
    }
}
// Auto-open in-progress stages.
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tp-stage-icon.inprogress').forEach(function(icon) {
        var hdr = icon.closest('.tp-stage-header');
        if (hdr) hdr.click();
    });
    // Also open first not-started stage if no in-progress stage found.
    var anyOpen = document.querySelector('.tp-tasks.open');
    if (!anyOpen) {
        var first = document.querySelector('.tp-stage-icon.notstarted');
        if (first) {
            var hdr = first.closest('.tp-stage-header');
            if (hdr) hdr.click();
        }
    }
});
</script>

<?php echo $OUTPUT->footer(); ?>
