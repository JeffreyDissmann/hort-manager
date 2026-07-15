<?php

declare(strict_types=1);

// The admin activity log (audit trail) page. Descriptions are composed in the
// frontend from these pieces so they follow the de/en toggle.
return [
    'title' => 'Activity log',
    'header' => 'Activity log',
    'intro' => 'Who changed what, and when — visible to admins only.',
    'empty' => 'No entries yet.',
    'system' => 'System',
    'newer' => 'Newer',
    'older' => 'Older',
    'bool_true' => 'yes',
    'bool_false' => 'no',

    // Event badge labels.
    'events' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'picked_up' => 'Picked up',
        'sent_home' => 'Sent home',
        'present' => 'Reset',
        'adjusted' => 'Day plan',
        'reset' => 'Reset',
        'rsvp_yes' => 'Accepted',
        'rsvp_no' => 'Declined',
        'guardians' => 'Guardians',
    ],

    // Subject nouns (shown for create/update/delete entries).
    'subjects' => [
        'child' => 'Child',
        'weekly_schedule' => 'Standard plan',
        'absence' => 'Absence',
        'excursion' => 'Excursion',
        'daily_program' => 'Daily program',
        'user' => 'User',
    ],

    // Changed-field names (shown in the diff).
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'is_admin' => 'Admin',
        'note' => 'Note',
        'date_of_birth' => 'Date of birth',
        'date' => 'Date',
        'weekday' => 'Weekday',
        'planned_time' => 'Time',
        'method' => 'Method',
        'companion' => 'Companion',
        'time_qualifier' => 'Time qualifier',
        'comment' => 'Comment',
        'lunch' => 'Lunch',
        'activity' => 'Activity',
        'homework_start' => 'Homework from',
        'homework_end' => 'Homework until',
        'homework_none' => 'No homework',
        'depart_at' => 'Departure',
        'return_at' => 'Return',
        'rsvp_deadline' => 'RSVP deadline',
    ],

    // Known enum values shown in the diff (method, qualifier, role, reason).
    'values' => [
        'picked_up' => 'picked up',
        'sent_home' => 'goes alone',
        'with_child' => 'goes with another child',
        'by' => 'by',
        'at' => 'exactly at',
        'from' => 'from',
        'sick' => 'sick',
        'away' => 'away',
        'staff' => 'Staff',
        'parent' => 'Parent',
    ],
];
