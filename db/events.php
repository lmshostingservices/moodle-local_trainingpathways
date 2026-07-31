<?php
/**
 * Training Pathways — event observer registration.
 *
 * Registers the course_completed observer so pathway tasks that are
 * linked to a Moodle course/quiz are automatically marked complete
 * when the student finishes the course in Moodle.
 *
 * @package    local_trainingpathways
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_trainingpathways\observer::course_completed',
        'internal'  => false,
    ],
];
