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
require_capability('local/trainingpathways:assign', $context);

$pathwayid  = optional_param('pathwayid', 0,  PARAM_INT);
$revokeid   = optional_param('revoke',    0,  PARAM_INT);
$filterpath = optional_param('filter_pathway', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/trainingpathways/assign.php'));
$PAGE->set_title(get_string('page_assign', 'local_trainingpathways'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/local/trainingpathways/styles.css');

// ── Revoke assignment ──────────────────────────────────────────────────────
if ($revokeid) {
    require_sesskey();
    $asgn = $DB->get_record('local_tp_assignments', ['id' => $revokeid]);
    if ($asgn) {
        \local_trainingpathways\progress_service::revoke($revokeid);
    }
    redirect(new moodle_url('/local/trainingpathways/assign.php'),
        get_string('revoke_assignment', 'local_trainingpathways'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Handle form submission ─────────────────────────────────────────────────
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $pid        = required_param('pathwayid',  PARAM_INT);
    $DB->get_record('local_tp_pathways', ['id' => $pid, 'status' => 'active'], '*', MUST_EXIST);
    $userids    = optional_param_array('userids', [], PARAM_INT);
    $targetdate = optional_param('targetdate', '', PARAM_TEXT);
    $now        = time();
    $tdts       = $targetdate ? strtotime($targetdate) : null;

    if (empty($userids)) {
        $notice = get_string('no_users_selected', 'local_trainingpathways');
    } else {
        $skipped = 0;
        foreach ($userids as $uid) {
            $uid = (int)$uid;
            if (!$uid) continue;
            $DB->get_record('user', ['id' => $uid, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
            // Check for existing active assignment.
            if ($DB->record_exists('local_tp_assignments', ['pathwayid'=>$pid,'userid'=>$uid,'status'=>'active'])) {
                $skipped++;
                continue;
            }
            \local_trainingpathways\progress_service::allocate($pid, $uid, $tdts ?: null);
        }
        $msg = get_string('assigned_ok', 'local_trainingpathways');
        if ($skipped) {
            $msg .= ' ' . get_string('already_assigned', 'local_trainingpathways');
        }
        redirect(new moodle_url('/local/trainingpathways/assign.php', ['pathwayid'=>$pid]),
            $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ── Load data ──────────────────────────────────────────────────────────────
$pathways = $DB->get_records('local_tp_pathways', ['status'=>'active'], 'name ASC');

// Current assignments (filtered if pathway selected)
$assignParams = [];
$assignWhere  = '1=1';
if ($filterpath) {
    $assignWhere  = 'a.pathwayid = :fpid';
    $assignParams['fpid'] = $filterpath;
}
$assignments = $DB->get_records_sql(
    "SELECT a.*, p.name AS pathwayname, p.colour AS pathwaycolour,
            " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS fullname,
            u.email
       FROM {local_tp_assignments} a
       JOIN {local_tp_pathways} p ON p.id = a.pathwayid
       JOIN {user} u              ON u.id = a.userid
      WHERE $assignWhere
      ORDER BY a.timeassigned DESC",
    $assignParams
);

// All site users for the multi-select (students/other roles)
$siteUsers = $DB->get_records_sql(
    "SELECT u.id, " . $DB->sql_concat('u.firstname',"' '",'u.lastname') . " AS fullname, u.email
       FROM {user} u
      WHERE u.deleted = 0 AND u.id != :guestid AND u.id != :siteid
      ORDER BY u.lastname ASC, u.firstname ASC",
    ['guestid' => $CFG->siteguest ?? 1, 'siteid' => $USER->id]
);

$selectedPathway = $pathwayid ? $DB->get_record('local_tp_pathways', ['id'=>$pathwayid]) : null;

echo $OUTPUT->header();
local_trainingpathways_render_header('assign');
?>

<div class="container-fluid" style="max-width:1000px">

<div class="tp-page-title"><?php echo get_string('page_assign','local_trainingpathways'); ?></div>
<div class="tp-page-subtitle"><?php echo get_string('assign_subtitle','local_trainingpathways'); ?></div>

<!-- Help box: how to assign pathways -->
<details class="tp-help-box" open>
  <summary>&#9432; How to assign pathways</summary>
  <div class="tp-help-box-body">
    <p class="tp-help-intro">Use this page to assign a training pathway to one or more employees. Once assigned, the employee will see the pathway in their <em>My Pathway</em> view and can begin working through their stages and tasks.</p>
    <ul class="tp-help-steps">
      <li class="tp-help-step"><span class="tp-help-step-num">1</span><span><strong>Select a pathway</strong> from the dropdown — only active pathways are listed here. If a pathway is not showing, check it hasn't been archived on the Manage Pathways page.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">2</span><span><strong>Select employees</strong> — type a name or email in the search box to filter the list, then tick the checkboxes next to each employee. You can select as many as you like at once.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">3</span><span><strong>Set a Target Date (optional)</strong> — this is the deadline by which the employee should complete the pathway. If the date passes and the pathway is not finished, the assignment will be flagged as overdue on the Team Dashboard.</span></li>
      <li class="tp-help-step"><span class="tp-help-step-num">4</span><span><strong>Click Assign</strong> — employees who already have an active assignment to the same pathway are automatically skipped (no duplicates created).</span></li>
    </ul>
    <div class="tp-help-tip"><span class="tp-help-tip-icon">&#128161;</span><span><strong>Removing an assignment:</strong> Find the employee in the <em>Current Assignments</em> table below, then click the <strong>&times;</strong> button to revoke their assignment. This also removes their recorded progress for that pathway.</span></div>
  </div>
</details>

<?php if ($notice): ?>
<div class="alert alert-warning"><?php echo s($notice); ?></div>
<?php endif; ?>

<!-- Assignment form -->
<div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:1.25rem;margin-bottom:1.5rem">
  <div style="font-weight:700;font-size:0.97rem;margin-bottom:1rem">Assign a Pathway</div>
  <form method="post" action="<?php echo (new moodle_url('/local/trainingpathways/assign.php'))->out(); ?>">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <div class="row">
      <div class="col-md-4 mb-3">
        <label style="font-weight:600;font-size:0.88rem"><?php echo get_string('select_pathway','local_trainingpathways'); ?> <span style="color:#dc3545">*</span></label>
        <select name="pathwayid" class="form-control" required>
          <option value="">— Select pathway —</option>
          <?php foreach ($pathways as $pw): ?>
            <option value="<?php echo $pw->id; ?>"
              <?php echo ($pathwayid == $pw->id) ? 'selected' : ''; ?>>
              <?php echo s($pw->name); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5 mb-3">
        <label style="font-weight:600;font-size:0.88rem"><?php echo get_string('select_users','local_trainingpathways'); ?> <span style="color:#dc3545">*</span></label>
        <input type="text" id="tp-emp-search" placeholder="Search by name or email&hellip;"
               class="form-control form-control-sm" autocomplete="off"
               style="margin-bottom:0.4rem">
        <div id="tp-emp-list" style="border:1px solid #ced4da;border-radius:4px;max-height:220px;overflow-y:auto;background:#fff">
          <?php foreach ($siteUsers as $u): ?>
          <label class="tp-emp-item">
            <input type="checkbox" name="userids[]" value="<?php echo $u->id; ?>">
            <span class="tp-emp-label">
              <span class="tp-emp-name"><?php echo s($u->fullname); ?></span>
              <span class="tp-emp-email"><?php echo s($u->email); ?></span>
            </span>
          </label>
          <?php endforeach; ?>
          <div id="tp-emp-noresult" style="display:none;padding:0.6rem 0.75rem;font-size:0.84rem;color:#6c757d">No employees match your search.</div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.35rem">
          <small id="tp-emp-count" style="color:#495057">0 selected</small>
          <span style="font-size:0.78rem">
            <a href="#" id="tp-emp-selectall" style="color:#1565c0;text-decoration:none">Select visible</a>
            &nbsp;&middot;&nbsp;
            <a href="#" id="tp-emp-clearall" style="color:#6c757d;text-decoration:none">Clear</a>
          </span>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <label style="font-weight:600;font-size:0.88rem"><?php echo get_string('target_date','local_trainingpathways'); ?></label>
        <input type="date" name="targetdate" class="form-control">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">
      &#128100; <?php echo get_string('assign_submit','local_trainingpathways'); ?>
    </button>
  </form>
</div>

<!-- Current assignments -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem">
  <div style="font-weight:700;font-size:0.97rem"><?php echo get_string('current_assignments','local_trainingpathways'); ?>
    <span style="font-weight:400;color:#6c757d;font-size:0.85rem">(<?php echo count($assignments); ?>)</span>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem">
    <small style="color:#6c757d">Filter:</small>
    <select onchange="location.href='<?php echo (new moodle_url('/local/trainingpathways/assign.php'))->out(); ?>&filter_pathway='+this.value"
            style="border:1px solid #ced4da;border-radius:4px;padding:0.25rem 0.5rem;font-size:0.85rem">
      <option value="0"><?php echo get_string('filter_all','local_trainingpathways'); ?></option>
      <?php foreach ($pathways as $pw): ?>
        <option value="<?php echo $pw->id; ?>" <?php echo ($filterpath==$pw->id)?'selected':''; ?>>
          <?php echo s($pw->name); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php if (empty($assignments)): ?>
  <div class="tp-empty-state"><div class="icon">&#128100;</div><p>No assignments yet.</p></div>
<?php else: ?>
<div style="overflow-x:auto">
<table class="tp-team-table">
  <thead>
    <tr>
      <th>Employee</th>
      <th>Pathway</th>
      <th>Assigned</th>
      <th>Target Date</th>
      <th>Progress</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($assignments as $a):
        $prog = local_trainingpathways_assignment_progress($a->id);
        $colour = local_trainingpathways_colour_class($a->pathwaycolour);
        $revokeUrl = new moodle_url('/local/trainingpathways/assign.php', [
            'revoke'  => $a->id,
            'sesskey' => sesskey(),
        ]);
        $targetStr = $a->targetdate ? userdate($a->targetdate, get_string('strftimedate', 'core_langconfig')) : '—';
        $isOverdue = $a->targetdate && $a->targetdate < time() && $prog['percent'] < 100;
    ?>
    <tr>
      <td>
        <strong><?php echo s($a->fullname); ?></strong><br>
        <small style="color:#6c757d"><?php echo s($a->email); ?></small>
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:0.4rem">
          <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $colour; ?>;flex-shrink:0"></span>
          <?php echo s($a->pathwayname); ?>
        </span>
      </td>
      <td><small><?php echo userdate($a->timeassigned, '%d %b %Y'); ?></small></td>
      <td>
        <?php if ($isOverdue): ?>
          <span style="color:#c62828;font-size:0.82rem">&#9888; <?php echo $targetStr; ?></span>
        <?php else: ?>
          <small><?php echo $targetStr; ?></small>
        <?php endif; ?>
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:0.5rem">
          <div class="tp-mini-bar-wrap">
            <div class="tp-mini-bar" style="width:<?php echo $prog['percent']; ?>%;background:<?php echo $colour; ?>"></div>
          </div>
          <small style="white-space:nowrap"><?php echo $prog['percent']; ?>%</small>
        </div>
      </td>
      <td>
        <a href="<?php echo (new moodle_url('/local/trainingpathways/view.php',['userid'=>$a->userid,'assignmentid'=>$a->id]))->out(); ?>"
           class="tp-btn-sm" title="View progress">&#128202;</a>
        <a href="<?php echo $revokeUrl->out(); ?>" class="tp-btn-sm danger"
           onclick="return confirm('<?php echo addslashes(get_string('revoke_confirm','local_trainingpathways')); ?>')"
           title="Revoke assignment">&#215;</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

</div>

<script>
(function () {
    var searchEl  = document.getElementById('tp-emp-search');
    var listEl    = document.getElementById('tp-emp-list');
    var countEl   = document.getElementById('tp-emp-count');
    var noResult  = document.getElementById('tp-emp-noresult');
    var selAllBtn = document.getElementById('tp-emp-selectall');
    var clearBtn  = document.getElementById('tp-emp-clearall');
    if (!searchEl || !listEl) return;

    function updateCount() {
        var n = listEl.querySelectorAll('input[type="checkbox"]:checked').length;
        countEl.textContent = n + (n === 1 ? ' employee selected' : ' employees selected');
    }

    function setCheckedStyle(item) {
        var cb = item.querySelector('input[type="checkbox"]');
        if (cb && cb.checked) {
            item.classList.add('tp-emp-item-checked');
        } else {
            item.classList.remove('tp-emp-item-checked');
        }
    }

    searchEl.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var items = listEl.querySelectorAll('.tp-emp-item');
        var visible = 0;
        items.forEach(function (item) {
            var show = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        noResult.style.display = (visible === 0) ? '' : 'none';
    });

    listEl.addEventListener('change', function (e) {
        if (e.target && e.target.type === 'checkbox') {
            var item = e.target.parentNode;
            if (item && item.classList.contains('tp-emp-item')) setCheckedStyle(item);
            updateCount();
        }
    });

    selAllBtn.addEventListener('click', function (e) {
        e.preventDefault();
        listEl.querySelectorAll('.tp-emp-item').forEach(function (item) {
            if (item.style.display !== 'none') {
                var cb = item.querySelector('input[type="checkbox"]');
                if (cb) { cb.checked = true; setCheckedStyle(item); }
            }
        });
        updateCount();
    });

    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        listEl.querySelectorAll('.tp-emp-item').forEach(function (item) {
            var cb = item.querySelector('input[type="checkbox"]');
            if (cb) { cb.checked = false; setCheckedStyle(item); }
        });
        updateCount();
    });

    // Validate at least one employee ticked before submit
    var form = searchEl.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var checked = listEl.querySelectorAll('input[type="checkbox"]:checked').length;
            if (checked === 0) {
                e.preventDefault();
                searchEl.focus();
                searchEl.style.borderColor = '#dc3545';
                countEl.style.color = '#dc3545';
                countEl.textContent = 'Please select at least one employee.';
                setTimeout(function () {
                    searchEl.style.borderColor = '';
                    countEl.style.color = '#495057';
                    updateCount();
                }, 3000);
            }
        });
    }
})();
</script>

<?php echo $OUTPUT->footer(); ?>
