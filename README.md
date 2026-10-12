# Training Pathways

Training Pathways lets authorised managers define staged training programs,
assign them to learners and review progress from verified course evidence and
required sign-offs.

## Features

- Organise a program into ordered stages and tasks.
- Assign programs to learners and preserve historical progress.
- Evaluate required tasks independently of optional tasks.
- Use verified Moodle course completion rather than learner self-certification
  for course tasks.
- Integrate program evidence with Course Recertification and Certificate Pro.

## Requirements

- Moodle 4.4 or later within the supported range declared in version.php.
- Course completion configured for course tasks that require that evidence.
- Appropriate management capabilities for program creation and allocation.
- Certification-cycle awards require the optional compatible Recertification
  and Certificate Pro plugins.

## Installation

1. Back up the Moodle database and plugin directory.
2. Install local_trainingpathways in local/trainingpathways.
3. Complete Moodle's administrator upgrade screen.
4. Review management permissions and create a program.
5. Optionally add the free Training Pathways block to learner dashboards.

## Usage

Create stages and tasks in the program editor, mark required work and sign-offs,
and allocate the program to the intended learners. Learners can review their
assigned programs and managers can review the evidence supporting completion.
Allocated definitions are protected so later edits cannot silently change
existing obligations; use a new program definition for a changed program.

Stage display order is not a site-wide course access restriction. Moodle
enrolment and access controls continue to govern direct access to courses.
Do not interpret a program task checkbox as verified course completion.

## Pricing

The one-time Pathways unlock is 50 LMS Labs credits or US$5 on Moodle Marketplace.
Its learner companion block is free. This plugin does not generate AI content.

## Licence

GNU GPL v3 or later.
