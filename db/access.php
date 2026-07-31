<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View own pathway progress (all logged-in users).
    'local/trainingpathways:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'     => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // View all employees' pathway progress (managers).
    'local/trainingpathways:viewall' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'     => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Create, edit and delete pathway definitions.
    'local/trainingpathways:manage' => [
        'riskbitmask' => RISK_SPAM | RISK_PERSONAL,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Assign pathways to users.
    'local/trainingpathways:assign' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Sign off manager-approval tasks.
    'local/trainingpathways:signoff' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
