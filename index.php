<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/trainingpathways/'));
$PAGE->set_pagelayout('standard');

if (has_capability('local/trainingpathways:manage', $context)) {
    redirect(new moodle_url('/local/trainingpathways/manage.php'));
} elseif (has_capability('local/trainingpathways:viewall', $context)) {
    redirect(new moodle_url('/local/trainingpathways/dashboard.php'));
} else {
    redirect(new moodle_url('/local/trainingpathways/view.php'));
}
