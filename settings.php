<?php
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
