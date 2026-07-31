<?php
/**
 * Training Pathways - version information.
 *
 * Visual employee training journey management plugin for Moodle.
 * Allows managers to assign structured pathways to employees and
 * track progress from recruitment through to ongoing professional development.
 *
 * @package    local_trainingpathways
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_trainingpathways';
$plugin->version   = 2026060302;
$plugin->requires  = 2022041900; // Moodle 4.0+
$plugin->supported = [400, 500];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.8';
