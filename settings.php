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

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_trainingpathways',
        get_string('pluginname', 'local_trainingpathways')
    );

    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_trainingpathways/heading',
        get_string('settings_heading', 'local_trainingpathways'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_trainingpathways/notify_overdue',
        get_string('notify_overdue',      'local_trainingpathways'),
        get_string('notify_overdue_desc', 'local_trainingpathways'),
        1
    ));
}
