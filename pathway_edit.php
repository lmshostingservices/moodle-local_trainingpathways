<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/trainingpathways:manage', $context);

$pathwayid = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/trainingpathways/pathway_edit.php', $pathwayid ? ['id'=>$pathwayid] : []));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/local/trainingpathways/styles.css');

// ── Load existing or create scaffold ──────────────────────────────────────
if ($pathwayid) {
    $pathway = $DB->get_record('local_tp_pathways', ['id' => $pathwayid], '*', MUST_EXIST);
    $stages  = local_trainingpathways_get_pathway_structure($pathwayid);
    $PAGE->set_title(get_string('edit_pathway', 'local_trainingpathways') . ': ' . $pathway->name);
} else {
    $pathway         = new stdClass();
    $pathway->id     = 0;
    $pathway->name   = '';
    $pathway->description = '';
    $pathway->colour = 'blue';
    $stages = [];
    $PAGE->set_title(get_string('page_new_pathway', 'local_trainingpathways'));
}

// ── All Moodle courses for course-type task dropdown ───────────────────────
$allCourses = $DB->get_records_menu('course', null, 'fullname ASC', 'id, fullname');
unset($allCourses[SITEID]); // remove site course

$taskTypes = [
    'course'   => get_string('tasktype_course',    'local_trainingpathways'),
    'quiz'     => get_string('tasktype_quiz',      'local_trainingpathways'),
    'video'    => get_string('tasktype_video',     'local_trainingpathways'),
    'document' => get_string('tasktype_document',  'local_trainingpathways'),
    'signoff'  => get_string('tasktype_signoff',   'local_trainingpathways'),
    'task'     => get_string('tasktype_task',      'local_trainingpathways'),
];

// ── Handle form submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $name        = required_param('pathway_name', PARAM_TEXT);
    $description = optional_param('pathway_description', '', PARAM_TEXT);
    $colour      = optional_param('pathway_colour', 'blue', PARAM_ALPHA);
    $now         = time();

    if ($pathwayid) {
        $DB->update_record('local_tp_pathways', (object)[
            'id'           => $pathwayid,
            'name'         => $name,
            'description'  => $description,
            'colour'       => $colour,
            'timemodified' => $now,
        ]);
    } else {
        $pathwayid = $DB->insert_record('local_tp_pathways', (object)[
            'name'         => $name,
            'description'  => $description,
            'colour'       => $colour,
            'status'       => 'active',
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
            'createdby'    => $USER->id,
        ]);
    }

    // Stages: delete all existing and re-insert from POST (simplest reliable approach)
    $existingStages = $DB->get_records('local_tp_stages', ['pathwayid' => $pathwayid]);
    foreach ($existingStages as $es) {
        $DB->delete_records('local_tp_tasks', ['stageid' => $es->id]);
    }
    $DB->delete_records('local_tp_stages', ['pathwayid' => $pathwayid]);

    $stageNames = $_POST['stage_name'] ?? [];
    $stageDescs = $_POST['stage_desc'] ?? [];

    foreach ($stageNames as $sIdx => $sName) {
        $sName = clean_param($sName, PARAM_TEXT);
        if ($sName === '') continue;

        $stageId = $DB->insert_record('local_tp_stages', (object)[
            'pathwayid'   => $pathwayid,
            'name'        => $sName,
            'description' => clean_param($stageDescs[$sIdx] ?? '', PARAM_TEXT),
            'sortorder'   => (int)$sIdx,
            'timecreated' => $now,
        ]);

        $taskNames  = $_POST['task_name'][$sIdx]  ?? [];
        $taskDescs  = $_POST['task_desc'][$sIdx]  ?? [];
        $taskTypes2 = $_POST['task_type'][$sIdx]  ?? [];
        $taskCourse = $_POST['task_course'][$sIdx]?? [];
        $taskUrl    = $_POST['task_url'][$sIdx]   ?? [];
        $taskReq    = $_POST['task_required'][$sIdx] ?? [];

        foreach ($taskNames as $tIdx => $tName) {
            $tName = clean_param($tName, PARAM_TEXT);
            if ($tName === '') continue;
            $tType = clean_param($taskTypes2[$tIdx] ?? 'task', PARAM_ALPHA);
            $tCourseId = (int)($taskCourse[$tIdx] ?? 0);
            $tUrl      = clean_param($taskUrl[$tIdx] ?? '', PARAM_URL);
            $tRequired = isset($taskReq[$tIdx]) ? 1 : 0;

            $DB->insert_record('local_tp_tasks', (object)[
                'stageid'     => $stageId,
                'name'        => $tName,
                'description' => clean_param($taskDescs[$tIdx] ?? '', PARAM_TEXT),
                'tasktype'    => $tType,
                'courseid'    => $tCourseId > 0 ? $tCourseId : null,
                'resourceurl' => $tUrl ?: null,
                'required'    => $tRequired,
                'sortorder'   => (int)$tIdx,
                'timecreated' => $now,
            ]);
        }
    }

    redirect(
        new moodle_url('/local/trainingpathways/manage.php'),
        get_string('pathway_saved', 'local_trainingpathways'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$colours = ['blue','green','purple','orange','teal','red','grey'];

echo $OUTPUT->header();
local_trainingpathways_render_header('manage');
?>

<div class="container-fluid" style="max-width:900px">

<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem">
  <a href="<?php echo (new moodle_url('/local/trainingpathways/manage.php'))->out(); ?>"
     style="color:#6c757d;text-decoration:none;font-size:0.9rem">&#8592; Manage Pathways</a>
  <span style="color:#dee2e6">/</span>
  <span style="font-weight:600;font-size:0.9rem">
    <?php echo $pathway->id ? s($pathway->name) : 'New Pathway'; ?>
  </span>
</div>

<!-- Help box: how to build a pathway -->
<details class="tp-help-box" open>
  <summary>&#9432; How to build a pathway</summary>
  <div class="tp-help-box-body">
    <p class="tp-help-intro">A pathway is made up of <strong>Stages</strong> (phases or milestones) which each contain one or more <strong>Tasks</strong> (individual actions your employee must complete). Stages are displayed to employees in order — like a timeline they work through step by step.</p>
    <ul class="tp-help-steps">
      <li class="tp-help-step"><span class="tp-help-step-num">1</span><span><strong>Fill in the Pathway Details</strong> below — give it a clear name (e.g. <em>Account Manager Onboarding</em>), an optional description, and pick a colour to identify it at a glance.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">2</span><span><strong>Add Stages</strong> — click <em>Add Stage</em> in the Stages &amp; Tasks section. Use stage names like <em>Week 1 — Induction</em>, <em>Month 1 — Core Skills</em>, or <em>Ongoing — Compliance</em>.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">3</span><span><strong>Add Tasks to each Stage</strong> — click <em>Add Task</em> inside a stage. Choose the task type that best matches the learning activity (see the Task Types guide below).</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">4</span><span><strong>Save</strong> when done — employees assigned to this pathway will see their updated stages and tasks immediately.</span></li>
    </ul>

    <div class="tp-help-section-label">Task types explained</div>
    <div class="tp-help-grid">
      <div class="tp-help-grid-item"><strong>&#127979; Moodle Course</strong>Links directly to an existing Moodle course. Employee sees an "Open Course" button. The pathway task ticks <em>automatically</em> the moment Moodle marks course completion — no manual step needed.</div>
      <div class="tp-help-grid-item"><strong>&#10067; Quiz</strong>Links to a Moodle course containing a quiz. Employee launches via "Open Course"; the pathway task ticks <em>automatically</em> when Moodle registers course completion (e.g. quiz passed).</div>
      <div class="tp-help-grid-item"><strong>&#127910; Training Video</strong>Provides a URL to an external video (YouTube, Vimeo, etc.). Employee clicks "Open Resource" and marks it complete themselves.</div>
      <div class="tp-help-grid-item"><strong>&#128196; Document / Policy</strong>Links to a document or policy URL (Google Doc, SharePoint, PDF). Employee clicks "Open Resource" and marks it complete themselves.</div>
      <div class="tp-help-grid-item"><strong>&#9997; Manager Sign-Off</strong>Requires explicit manager approval to mark complete. Best for tasks observed in person or witnessed directly — no file upload needed. The employee clicks "Request Sign-Off" and the manager approves from their dashboard.</div>
      <div class="tp-help-grid-item"><strong>&#9989; General Task</strong>A free-form task with no linked resource. The employee marks it complete themselves (e.g. "Meet with your buddy", "Set up your workstation").</div>
    </div>

    <div class="tp-help-section-label" style="margin-top:1rem">&#128196; Practical evidence tasks — Assignment Benchmarks</div>
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:0.85rem 1rem;margin-top:0.5rem;font-size:0.85rem;color:#166534;line-height:1.55">
      <p style="margin:0 0 0.6rem 0">For practical workplace tasks where employees must <strong>upload evidence</strong> (files, photos, written statements) and be assessed against specific performance criteria, use a <strong>Moodle Assignment</strong> with the <strong>Assignment Benchmarks</strong> grading method — then link it here as a <strong>Moodle Course</strong> task.</p>
      <p style="margin:0 0 0.5rem 0;font-weight:600">How to set it up:</p>
      <ol style="margin:0 0 0.6rem 0;padding-left:1.3rem">
        <li style="margin-bottom:0.3rem">Install the <strong>Assignment Benchmarks</strong> plugin (<code>gradingform_benchmarks</code>) from the AI Grader plugin library if not already installed.</li>
        <li style="margin-bottom:0.3rem">In a Moodle course, add a <strong>Moodle Assignment</strong> activity and set the submission type to <em>File submissions</em> (and/or Online text).</li>
        <li style="margin-bottom:0.3rem">Under <em>Grade &rarr; Grading method</em>, select <strong>Benchmarks</strong>. Define each performance criterion with its S / NYS descriptors and save the rubric.</li>
        <li style="margin-bottom:0.3rem">Set <em>Activity completion &rarr; Completion tracking</em> to <strong>Show activity as complete when conditions are met</strong> and tick <strong>Require grade</strong>.</li>
        <li>Back in Training Pathways, add a <strong>Moodle Course</strong> task linked to that course. When the employee's Benchmarks assignment is graded Satisfactory across all criteria, Moodle fires course completion and the pathway task ticks automatically via the Training Pathways event observer — no manual step needed.</li>
      </ol>
      <p style="margin:0;font-size:0.82rem">&#128161; <strong>Tip:</strong> You can group multiple related assignments into a single course (e.g. "WHS Practical Tasks") so the pathway task represents the whole practical unit, or use one course per assignment for fine-grained control. The pathway tracks completion at the course level.</p>
    </div>

    <div class="tp-help-section-label" style="margin-top:1rem">&#10067; Sign-Off vs. Assignment Benchmarks — which to use?</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:0.5rem;font-size:0.83rem">
      <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:0.75rem">
        <p style="margin:0 0 0.4rem 0;font-weight:700;color:#1e40af">&#9997; Use Manager Sign-Off when:</p>
        <ul style="margin:0;padding-left:1.2rem;color:#1e3a8a">
          <li>The manager observes or witnesses the task in person</li>
          <li>No file or evidence upload is required</li>
          <li>You want a quick, single-click approval flow</li>
          <li>Examples: Induction presentation attended, buddy meeting completed, workstation set-up confirmed</li>
        </ul>
      </div>
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:0.75rem">
        <p style="margin:0 0 0.4rem 0;font-weight:700;color:#166534">&#128196; Use Assignment Benchmarks when:</p>
        <ul style="margin:0;padding-left:1.2rem;color:#14532d">
          <li>The employee must upload evidence (files, photos, documents)</li>
          <li>The assessment has multiple performance criteria to judge individually</li>
          <li>You need a full audit trail for compliance or RTO purposes</li>
          <li>Examples: WHS risk assessment, first aid practical, tool operation check, customer service role-play</li>
        </ul>
      </div>
    </div>

    <div class="tp-help-tip" style="margin-top:0.75rem"><span class="tp-help-tip-icon">&#128161;</span><span><strong>Required vs. Optional:</strong> Tick the <em>Required</em> checkbox on tasks that must be completed for the employee to finish the pathway. Optional tasks appear on the timeline but do not affect the completion percentage.</span></div>
  </div>
</details>

<form method="post" action="<?php echo (new moodle_url('/local/trainingpathways/pathway_edit.php', $pathway->id ? ['id'=>$pathway->id] : []))->out(); ?>" id="tp-editor-form">
<input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

<!-- Section: Pathway details -->
<div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:1.25rem;margin-bottom:1.25rem">
  <div style="font-weight:700;font-size:1rem;margin-bottom:1rem;color:#212529">
    &#9881; <?php echo get_string('section_details', 'local_trainingpathways'); ?>
  </div>

  <div class="form-group mb-3">
    <label for="pathway_name" style="font-weight:600;font-size:0.88rem">
      <?php echo get_string('pathway_name','local_trainingpathways'); ?> <span style="color:#dc3545">*</span>
    </label>
    <input type="text" id="pathway_name" name="pathway_name"
           value="<?php echo s($pathway->name); ?>"
           class="form-control" required placeholder="e.g. Account Manager Pathway">
  </div>

  <div class="form-group mb-3">
    <label for="pathway_desc" style="font-weight:600;font-size:0.88rem">
      <?php echo get_string('pathway_description','local_trainingpathways'); ?>
    </label>
    <textarea id="pathway_desc" name="pathway_description"
              class="form-control" rows="2"
              placeholder="Brief description (optional)"><?php echo s($pathway->description); ?></textarea>
  </div>

  <div class="form-group mb-0">
    <label style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.4rem">
      <?php echo get_string('pathway_colour','local_trainingpathways'); ?>
    </label>
    <div class="tp-colour-swatches">
      <?php foreach ($colours as $c):
          $hex = local_trainingpathways_colour_class($c);
          $sel = ($pathway->colour === $c) ? 'selected' : '';
      ?>
        <div class="tp-colour-swatch <?php echo $sel; ?>"
             style="background:<?php echo $hex; ?>"
             data-colour="<?php echo $c; ?>"
             title="<?php echo ucfirst($c); ?>"
             onclick="tpSelectColour(this)"></div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="pathway_colour" id="pathway_colour_input"
           value="<?php echo s($pathway->colour); ?>">
  </div>
</div>

<!-- Section: Stages & Tasks -->
<div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:1.25rem;margin-bottom:1.25rem">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
    <div style="font-weight:700;font-size:1rem;color:#212529">
      &#128218; <?php echo get_string('section_stages', 'local_trainingpathways'); ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary"
            onclick="tpAddStage()">&#43; <?php echo get_string('add_stage','local_trainingpathways'); ?></button>
  </div>

  <div id="tp-stages-list">
    <?php if (empty($stages)): ?>
      <div id="tp-no-stages-msg" style="text-align:center;color:#6c757d;padding:1.5rem 0;font-size:0.9rem">
        No stages yet. Click <strong>Add Stage</strong> to begin building your pathway.
      </div>
    <?php else:
        foreach ($stages as $si => $stage): ?>
      <div class="tp-editor-stage" id="tp-stage-block-<?php echo $si; ?>">
        <div class="tp-editor-stage-hdr" onclick="tpToggleStage(<?php echo $si; ?>)">
          <span style="font-size:0.85rem;color:#adb5bd">&#9776;</span>
          <input type="text" name="stage_name[<?php echo $si; ?>]"
                 value="<?php echo s($stage->name); ?>"
                 placeholder="Stage name" required
                 style="flex:1;border:none;background:transparent;font-weight:600;font-size:0.9rem;outline:none"
                 onclick="event.stopPropagation()">
          <button type="button" class="tp-btn-sm danger"
                  style="padding:0.15rem 0.5rem;font-size:0.75rem"
                  onclick="event.stopPropagation();tpRemoveStage(<?php echo $si; ?>)">&#215;</button>
          <span id="tp-stage-arrow-<?php echo $si; ?>" style="color:#6c757d;font-size:0.8rem">&#9660;</span>
        </div>
        <div class="tp-editor-stage-body" id="tp-stage-body-<?php echo $si; ?>">
          <div class="mb-2">
            <input type="text" name="stage_desc[<?php echo $si; ?>]"
                   value="<?php echo s($stage->description); ?>"
                   placeholder="Stage description (optional)"
                   class="form-control form-control-sm">
          </div>

          <div id="tp-task-list-<?php echo $si; ?>" style="margin-bottom:0.5rem">
            <?php foreach ($stage->tasks as $ti => $task): ?>
              <?php echo tpTaskRowHtml($si, $ti, $task, $taskTypes, $allCourses); ?>
            <?php endforeach; ?>
          </div>

          <button type="button" class="tp-btn-sm"
                  onclick="tpAddTask(<?php echo $si; ?>)"
                  style="font-size:0.8rem">&#43; <?php echo get_string('add_task','local_trainingpathways'); ?></button>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Submit -->
<div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;margin-bottom:2rem">
  <button type="submit" class="btn btn-success">
    &#10003; <?php echo get_string('save_pathway','local_trainingpathways'); ?>
  </button>
  <a href="<?php echo (new moodle_url('/local/trainingpathways/manage.php'))->out(); ?>"
     class="btn btn-outline-secondary"><?php echo get_string('cancel','local_trainingpathways'); ?></a>
</div>
</form>
</div>

<?php
// PHP helper to render a task row (used above and via inline JS template)
function tpTaskRowHtml(int $si, int $ti, $task, array $taskTypes, array $allCourses): string {
    $o  = '<div class="tp-editor-task-row" id="tp-task-' . $si . '-' . $ti . '">';

    // ── Top row: handle + name + delete ──
    $o .= '<div class="tp-editor-task-top-row">';
    $o .= '<span class="handle">&#9776;</span>';
    $o .= '<input type="text" name="task_name[' . $si . '][' . $ti . ']" '
        . 'value="' . s($task->name ?? '') . '" placeholder="Task name" required '
        . 'style="flex:1;min-width:0;border:1px solid #ced4da;border-radius:4px;padding:0.3rem 0.5rem;font-size:0.88rem">';
    $o .= '<button type="button" class="tp-btn-sm danger" style="padding:0.15rem 0.5rem;font-size:0.75rem;flex-shrink:0" '
        . 'onclick="tpRemoveTask(' . $si . ',' . $ti . ')">&#215;</button>';
    $o .= '</div>';

    // ── Bottom row: type + course/url + required ──
    $o .= '<div class="tp-editor-task-bottom-row">';

    // Task type select
    $o .= '<select name="task_type[' . $si . '][' . $ti . ']" '
        . 'onchange="tpTaskTypeChanged(this,' . $si . ',' . $ti . ')" '
        . 'style="border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.4rem;font-size:0.82rem">';
    foreach ($taskTypes as $tv => $tl) {
        $sel = (($task->tasktype ?? 'task') === $tv) ? ' selected' : '';
        $o .= '<option value="' . $tv . '"' . $sel . '>' . $tl . '</option>';
    }
    $o .= '</select>';

    // Course dropdown (shown for course/quiz types)
    $showCourse = in_array($task->tasktype ?? '', ['course','quiz']);
    $o .= '<select name="task_course[' . $si . '][' . $ti . ']" '
        . 'id="tp-tcourse-' . $si . '-' . $ti . '" '
        . 'style="flex:1;min-width:0;border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.4rem;font-size:0.82rem;'
        . ($showCourse ? '' : 'display:none') . '">';
    $o .= '<option value="0">— Select course —</option>';
    foreach ($allCourses as $cid => $cname) {
        $sel = (($task->courseid ?? 0) == $cid) ? ' selected' : '';
        $o .= '<option value="' . $cid . '"' . $sel . '>' . s($cname) . '</option>';
    }
    $o .= '</select>';

    // URL field (shown for video/document types)
    $showUrl = in_array($task->tasktype ?? '', ['video','document']);
    $o .= '<input type="url" name="task_url[' . $si . '][' . $ti . ']" '
        . 'id="tp-turl-' . $si . '-' . $ti . '" '
        . 'value="' . s($task->resourceurl ?? '') . '" '
        . 'placeholder="https://..." '
        . 'style="flex:1;min-width:0;border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.5rem;font-size:0.82rem;'
        . ($showUrl ? '' : 'display:none') . '">';

    // Required checkbox
    $checked = ($task->required ?? 1) ? ' checked' : '';
    $o .= '<label style="font-size:0.78rem;white-space:nowrap;display:flex;align-items:center;gap:0.25rem;margin:0">'
        . '<input type="checkbox" name="task_required[' . $si . '][' . $ti . ']" value="1"' . $checked . '> Required</label>';

    $o .= '</div>';
    $o .= '</div>';
    return $o;
}
?>

<script>
// Stage counter (tracks highest stage index used)
var _tpStageCount = <?php echo max(count($stages), 0); ?>;
var _tpTaskCounts = <?php
    $tc = [];
    foreach ($stages as $si => $stage) { $tc[$si] = count($stage->tasks); }
    echo json_encode($tc);
?>;

var _tpAllCourses = <?php echo json_encode(array_map('s', $allCourses)); ?>;
var _tpTaskTypes  = <?php echo json_encode($taskTypes); ?>;

function tpSelectColour(el) {
    document.querySelectorAll('.tp-colour-swatch').forEach(function(s) { s.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('pathway_colour_input').value = el.dataset.colour;
}

function tpToggleStage(si) {
    var body  = document.getElementById('tp-stage-body-' + si);
    var arrow = document.getElementById('tp-stage-arrow-' + si);
    if (!body) return;
    if (body.style.display === 'none') {
        body.style.display = '';
        if (arrow) arrow.innerHTML = '&#9660;';
    } else {
        body.style.display = 'none';
        if (arrow) arrow.innerHTML = '&#9654;';
    }
}

function tpRemoveStage(si) {
    if (!confirm('<?php echo addslashes(get_string('confirm_delete_stage','local_trainingpathways')); ?>')) return;
    var el = document.getElementById('tp-stage-block-' + si);
    if (el) el.remove();
    var msg = document.getElementById('tp-no-stages-msg');
    if (msg) {
        var remaining = document.querySelectorAll('[id^="tp-stage-block-"]');
        if (remaining.length === 0) msg.style.display = '';
    }
}

function tpAddStage() {
    var msg = document.getElementById('tp-no-stages-msg');
    if (msg) msg.style.display = 'none';
    var si  = _tpStageCount++;
    _tpTaskCounts[si] = 0;
    var html = '<div class="tp-editor-stage" id="tp-stage-block-' + si + '">'
        + '<div class="tp-editor-stage-hdr" onclick="tpToggleStage(' + si + ')">'
        + '<span style="font-size:0.85rem;color:#adb5bd">&#9776;</span>'
        + '<input type="text" name="stage_name[' + si + ']" placeholder="Stage name" required'
        + ' style="flex:1;border:none;background:transparent;font-weight:600;font-size:0.9rem;outline:none"'
        + ' onclick="event.stopPropagation()">'
        + '<button type="button" class="tp-btn-sm danger" style="padding:0.15rem 0.5rem;font-size:0.75rem"'
        + ' onclick="event.stopPropagation();tpRemoveStage(' + si + ')">&#215;</button>'
        + '<span id="tp-stage-arrow-' + si + '" style="color:#6c757d;font-size:0.8rem">&#9660;</span>'
        + '</div>'
        + '<div class="tp-editor-stage-body" id="tp-stage-body-' + si + '">'
        + '<div class="mb-2"><input type="text" name="stage_desc[' + si + ']" placeholder="Stage description (optional)"'
        + ' class="form-control form-control-sm"></div>'
        + '<div id="tp-task-list-' + si + '" style="margin-bottom:0.5rem"></div>'
        + '<button type="button" class="tp-btn-sm" onclick="tpAddTask(' + si + ')" style="font-size:0.8rem">'
        + '&#43; Add Task</button>'
        + '</div></div>';
    document.getElementById('tp-stages-list').insertAdjacentHTML('beforeend', html);
    // Focus the new stage name field
    setTimeout(function() {
        var inp = document.querySelector('#tp-stage-block-' + si + ' input[name^="stage_name"]');
        if (inp) inp.focus();
    }, 50);
}

function tpBuildTaskRow(si, ti) {
    // ── Top row: handle + name + delete ──
    var html = '<div class="tp-editor-task-row" id="tp-task-' + si + '-' + ti + '">'
        + '<div class="tp-editor-task-top-row">'
        + '<span class="handle">&#9776;</span>'
        + '<input type="text" name="task_name[' + si + '][' + ti + ']" placeholder="Task name" required'
        + ' style="flex:1;min-width:0;border:1px solid #ced4da;border-radius:4px;padding:0.3rem 0.5rem;font-size:0.88rem">'
        + '<button type="button" class="tp-btn-sm danger" style="padding:0.15rem 0.5rem;font-size:0.75rem;flex-shrink:0"'
        + ' onclick="tpRemoveTask(' + si + ',' + ti + ')">&#215;</button>'
        + '</div>'
        // ── Bottom row: type + course/url + required ──
        + '<div class="tp-editor-task-bottom-row">'
        + '<select name="task_type[' + si + '][' + ti + ']" onchange="tpTaskTypeChanged(this,' + si + ',' + ti + ')"'
        + ' style="border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.4rem;font-size:0.82rem">';
    for (var tv in _tpTaskTypes) {
        html += '<option value="' + tv + '">' + _tpTaskTypes[tv] + '</option>';
    }
    html += '</select>'
        + '<select name="task_course[' + si + '][' + ti + ']" id="tp-tcourse-' + si + '-' + ti + '"'
        + ' style="display:none;flex:1;min-width:0;border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.4rem;font-size:0.82rem">'
        + '<option value="0">— Select course —</option>';
    for (var cid in _tpAllCourses) {
        html += '<option value="' + cid + '">' + _tpAllCourses[cid] + '</option>';
    }
    html += '</select>'
        + '<input type="url" name="task_url[' + si + '][' + ti + ']" id="tp-turl-' + si + '-' + ti + '"'
        + ' placeholder="https://..."'
        + ' style="display:none;flex:1;min-width:0;border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.5rem;font-size:0.82rem">'
        + '<label style="font-size:0.78rem;white-space:nowrap;display:flex;align-items:center;gap:0.25rem;margin:0">'
        + '<input type="checkbox" name="task_required[' + si + '][' + ti + ']" value="1" checked> Required</label>'
        + '</div>'
        + '</div>';
    return html;
}

function tpAddTask(si) {
    if (_tpTaskCounts[si] === undefined) _tpTaskCounts[si] = 0;
    var ti   = _tpTaskCounts[si]++;
    var list = document.getElementById('tp-task-list-' + si);
    if (!list) return;
    list.insertAdjacentHTML('beforeend', tpBuildTaskRow(si, ti));
    setTimeout(function() {
        var inp = document.querySelector('#tp-task-' + si + '-' + ti + ' input[type="text"]');
        if (inp) inp.focus();
    }, 50);
}

function tpRemoveTask(si, ti) {
    if (!confirm('<?php echo addslashes(get_string('confirm_delete_task','local_trainingpathways')); ?>')) return;
    var el = document.getElementById('tp-task-' + si + '-' + ti);
    if (el) el.remove();
}

function tpTaskTypeChanged(sel, si, ti) {
    var type    = sel.value;
    var course  = document.getElementById('tp-tcourse-' + si + '-' + ti);
    var urlInp  = document.getElementById('tp-turl-' + si + '-' + ti);
    if (course) course.style.display = (type === 'course' || type === 'quiz') ? '' : 'none';
    if (urlInp) urlInp.style.display = (type === 'video'  || type === 'document') ? '' : 'none';
}
</script>

<?php echo $OUTPUT->footer(); ?>
