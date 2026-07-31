/**
 * Training Pathways — AMD module.
 *
 * Enhances the employee pathway view with AJAX task-toggle
 * (no full page reload) and animated progress bar updates.
 *
 * @module     local_trainingpathways/pathways
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

    'use strict';

    var _sesskey = '';
    var _ajaxUrl = '';

    /**
     * Initialise the module — called by PHP inline script on view.php.
     *
     * @param {string} sesskey  Moodle session key
     * @param {string} ajaxUrl  URL of ajax.php
     */
    function init(sesskey, ajaxUrl) {
        _sesskey = sesskey;
        _ajaxUrl = ajaxUrl;

        // Replace all task-toggle forms with AJAX buttons.
        document.querySelectorAll('form[data-tp-task]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var assignmentid = form.querySelector('[name="assignmentid"]').value;
                var taskid       = form.querySelector('[name="taskid"]').value;
                var status       = form.querySelector('[name="status"]').value;
                markTask(parseInt(assignmentid), parseInt(taskid), status, form);
            });
        });
    }

    /**
     * Send mark_task AJAX request and update UI on success.
     */
    function markTask(assignmentid, taskid, status, form) {
        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        }

        var data = new FormData();
        data.append('action',       'mark_task');
        data.append('assignmentid', assignmentid);
        data.append('taskid',       taskid);
        data.append('status',       status);
        data.append('sesskey',      _sesskey);

        fetch(_ajaxUrl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (!json.ok) {
                    throw new Error(json.error || 'Unknown error');
                }
                updateTaskUI(form, status, json.progress);
            })
            .catch(function(err) {
                Notification.addNotification({
                    message: 'Error updating task: ' + err.message,
                    type: 'error'
                });
                if (btn) { btn.disabled = false; btn.style.opacity = ''; }
            });
    }

    /**
     * Update the task row UI and progress bar after a successful AJAX call.
     */
    function updateTaskUI(form, newStatus, progress) {
        var taskRow  = form.closest('.tp-task-row');
        var taskName = taskRow ? taskRow.querySelector('.tp-task-name') : null;

        if (newStatus === 'complete') {
            if (taskName) taskName.classList.add('complete');
            // Swap the hidden status value for the next toggle.
            var statusInput = form.querySelector('[name="status"]');
            if (statusInput) statusInput.value = 'notstarted';
            // Swap button text.
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.classList.remove('success');
                btn.textContent = 'Mark Incomplete';
                btn.disabled    = false;
                btn.style.opacity = '';
            }
        } else {
            if (taskName) taskName.classList.remove('complete');
            var statusInput2 = form.querySelector('[name="status"]');
            if (statusInput2) statusInput2.value = 'complete';
            var btn2 = form.querySelector('button[type="submit"]');
            if (btn2) {
                btn2.classList.add('success');
                btn2.textContent = 'Mark Complete';
                btn2.disabled    = false;
                btn2.style.opacity = '';
            }
        }

        // Update progress bar and percentage.
        if (progress) {
            var bar = document.querySelector('.tp-progress-bar');
            var pct = document.querySelector('.tp-progress-percent');
            if (bar) bar.style.width = progress.percent + '%';
            if (pct) pct.textContent = progress.percent + '%';
        }

        // Update stage badges.
        updateStageBadges();
    }

    /**
     * Re-calculate and update stage status badges based on current task states.
     */
    function updateStageBadges() {
        document.querySelectorAll('.tp-stage').forEach(function(stageEl) {
            var taskRows = stageEl.querySelectorAll('.tp-task-row');
            if (!taskRows.length) return;
            var total    = taskRows.length;
            var complete = 0;
            taskRows.forEach(function(row) {
                var nm = row.querySelector('.tp-task-name');
                if (nm && nm.classList.contains('complete')) complete++;
            });
            var icon  = stageEl.querySelector('.tp-stage-icon');
            var badge = stageEl.querySelector('.tp-stage-badge');
            var status;
            if (complete === total) {
                status = 'complete';
            } else if (complete > 0) {
                status = 'inprogress';
            } else {
                status = 'notstarted';
            }
            if (icon) {
                icon.className = 'tp-stage-icon ' + status;
                icon.innerHTML = status === 'complete' ? '&#10003;' : (status === 'inprogress' ? '&#9654;' : icon.innerHTML);
            }
            if (badge) {
                badge.className = 'tp-stage-badge tp-badge-' + status;
                badge.textContent = status === 'complete' ? 'Complete' : (status === 'inprogress' ? 'In Progress' : 'Not Started');
            }
        });
    }

    return {
        init: init
    };
});
