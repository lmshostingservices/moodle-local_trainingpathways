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

defined('MOODLE_INTERNAL') || die();

$string['allocatedpathwaylocked'] = 'This pathway has learner history and cannot be deleted or edited. Archive it and create a new pathway for changed requirements.';
$string['pluginname']              = 'Training Pathways';
$string['trainingpathways']        = 'Training Pathways';
$string['trainingpathways:view']   = 'View own training pathway';
$string['trainingpathways:viewall']= 'View all employees training pathways';
$string['trainingpathways:manage'] = 'Manage training pathways';
$string['trainingpathways:assign'] = 'Assign pathways to employees';
$string['trainingpathways:signoff']= 'Sign off manager approval tasks';

// Navigation
$string['nav_my_pathway']   = 'My Pathway';
$string['nav_team']         = 'Team Dashboard';
$string['nav_manage']       = 'Manage Pathways';
$string['nav_assign']       = 'Assign Pathways';

// Dashboard
$string['page_dashboard']        = 'Team Training Dashboard';
$string['dashboard_subtitle']    = 'Monitor your team\'s training progress across all active pathways.';
$string['col_employee']          = 'Employee';
$string['col_pathway']           = 'Pathway';
$string['col_progress']          = 'Progress';
$string['col_stage']             = 'Current Stage';
$string['col_assigned']          = 'Assigned';
$string['col_target']            = 'Target Date';
$string['col_actions']           = 'Actions';
$string['no_assignments']        = 'No pathway assignments found.';
$string['filter_pathway']        = 'Filter by pathway';
$string['filter_all']            = 'All pathways';
$string['stat_total']            = 'Assigned Employees';
$string['stat_complete']         = 'Completed';
$string['stat_overdue']          = 'Overdue';
$string['stat_inprogress']       = 'In Progress';

// Manage pathways
$string['page_manage']           = 'Manage Pathways';
$string['manage_subtitle']       = 'Create and manage training pathways for your organisation.';
$string['create_pathway']        = 'Create New Pathway';
$string['edit_pathway']          = 'Edit Pathway';
$string['delete_pathway']        = 'Delete Pathway';
$string['archive_pathway']       = 'Archive Pathway';
$string['unarchive_pathway']     = 'Restore Pathway';
$string['pathway_name']          = 'Pathway Name';
$string['pathway_description']   = 'Description';
$string['pathway_colour']        = 'Colour';
$string['pathway_status']        = 'Status';
$string['pathway_stages']        = 'Stages';
$string['pathway_tasks']         = 'Tasks';
$string['pathway_assigned']      = 'Assigned To';
$string['pathway_saved']         = 'Pathway saved successfully.';
$string['pathway_deleted']       = 'Pathway deleted.';
$string['pathway_archived']      = 'Pathway archived.';
$string['pathway_unarchived']    = 'Pathway restored.';
$string['no_pathways']           = 'No pathways have been created yet.';
$string['status_active']         = 'Active';
$string['status_archived']       = 'Archived';

// Pathway editor
$string['page_edit_pathway']     = 'Edit Pathway';
$string['page_new_pathway']      = 'New Pathway';
$string['section_details']       = 'Pathway Details';
$string['section_stages']        = 'Stages & Tasks';
$string['add_stage']             = 'Add Stage';
$string['stage_name']            = 'Stage Name';
$string['stage_description']     = 'Stage Description (optional)';
$string['delete_stage']          = 'Delete Stage';
$string['add_task']              = 'Add Task';
$string['task_name']             = 'Task Name';
$string['task_description']      = 'Description (optional)';
$string['task_type']             = 'Task Type';
$string['task_course']           = 'Linked Moodle Course';
$string['task_url']              = 'Resource URL';
$string['task_required']         = 'Required to advance stage';
$string['delete_task']           = 'Delete Task';
$string['save_pathway']          = 'Save Pathway';
$string['cancel']                = 'Cancel';
$string['confirm_delete_stage']  = 'Delete this stage and all its tasks?';
$string['confirm_delete_task']   = 'Delete this task?';
$string['confirm_delete_pathway']= 'Permanently delete this pathway and all assignment data?';

// Task types
$string['tasktype_course']       = 'Moodle Course';
$string['tasktype_quiz']         = 'Quiz';
$string['tasktype_video']        = 'Training Video';
$string['tasktype_document']     = 'Document / Policy';
$string['tasktype_signoff']      = 'Manager Sign-Off';
$string['tasktype_task']         = 'General Task';

// Assign page
$string['page_assign']           = 'Assign Pathways';
$string['assign_subtitle']       = 'Assign a training pathway to one or more employees.';
$string['select_pathway']        = 'Select Pathway';
$string['select_users']          = 'Select Employees';
$string['target_date']           = 'Target Completion Date (optional)';
$string['assign_submit']         = 'Assign Pathway';
$string['assigned_ok']           = 'Pathway assigned successfully.';
$string['already_assigned']      = 'One or more employees already have this pathway — existing assignments were skipped.';
$string['no_users_selected']     = 'Please select at least one employee.';
$string['current_assignments']   = 'Current Assignments';
$string['revoke_assignment']     = 'Revoke';
$string['revoke_confirm']        = 'Remove this pathway assignment?';

// Employee view
$string['page_my_pathway']       = 'My Training Pathway';
$string['no_pathway_assigned']   = 'You have not been assigned a training pathway yet. Please contact your manager.';
$string['overall_progress']      = 'Overall Progress';
$string['stage_complete']        = 'Complete';
$string['stage_inprogress']      = 'In Progress';
$string['stage_notstarted']      = 'Not Started';
$string['task_mark_complete']    = 'Mark Complete';
$string['task_mark_incomplete']  = 'Mark Incomplete';
$string['task_open_course']      = 'Open Course';
$string['task_open_resource']    = 'Open Resource';
$string['task_awaiting_signoff'] = 'Awaiting Manager Sign-Off';
$string['task_signed_off']       = 'Signed Off';
$string['task_optional']         = 'Optional';
$string['task_required_badge']   = 'Required';
$string['signoff_requested']     = 'Sign-off requested.';
$string['task_updated']          = 'Task updated.';
$string['select_pathway_view']   = 'View pathway';
$string['assigned_on']           = 'Assigned';
$string['target_date_label']     = 'Target Date';
$string['days_remaining']        = '{$a} days remaining';
$string['overdue_by']            = 'Overdue by {$a} days';

// Manager sign-off
$string['signoff_title']         = 'Sign Off Task';
$string['signoff_employee']      = 'Employee';
$string['signoff_task']          = 'Task';
$string['signoff_notes']         = 'Notes (optional)';
$string['signoff_approve']       = 'Approve & Sign Off';
$string['signoff_done']          = 'Task signed off.';

// Progress reports
$string['page_report']           = 'Training Pathway Report';

// Settings
$string['settings_heading']      = 'Training Pathways Settings';
$string['notify_overdue']        = 'Send overdue notifications';
$string['notify_overdue_desc']   = 'Send automated email notifications when employees have overdue pathway tasks.';

$string['privacy:metadata'] = 'The local_trainingpathways plugin does not store any personal data.';
