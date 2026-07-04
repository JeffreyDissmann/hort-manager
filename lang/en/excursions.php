<?php

declare(strict_types=1);

// Ausflug (excursion) screens: planning, the parent RSVP poll and history.
return [
    'heading' => 'Excursions',
    'poll_title' => 'Excursions – Poll',
    'plan_title' => 'Plan an excursion',
    'edit_heading' => 'Edit excursion',
    'edit_title' => 'Edit :name',

    'intro' => 'Plan an excursion and set a deadline – all parents are automatically asked whether their child is coming along. On the day of the trip the confirmed children appear on the daily board.',

    'upcoming_heading' => 'Upcoming excursions',
    'past_heading' => 'Past excursions',
    'past_hint' => 'Excursions that already took place, with the children who joined.',
    'none_planned' => 'No excursions are currently planned.',
    'none_planned_create' => 'No excursions are currently planned. Add the first one above.',

    'depart' => 'Departure',
    'return' => 'Return',
    'time_range' => ':from–:to',
    'time_from' => 'from :time',

    'poll_closed' => 'Poll closed',
    'poll_until' => 'Poll until :date',
    'deadline_today' => 'Today is the last day to respond',
    'deadline_tomorrow' => 'Please respond by tomorrow',
    'deadline_days' => 'Please respond by :date (:n days left)',

    'answer_yes' => 'Coming along',
    'answer_no' => 'Not joining',

    'status_open' => 'still open',
    'status_confirmed' => 'confirmed',
    'status_declined' => 'declined',
    'open' => 'open',
    'joined' => 'joining',
    'not_joined' => 'not joining',
    'no_response' => 'no response',

    'joining_count' => ':count joining',
    'joining_count_past' => ':count joined',
    'pending_count' => ':count open',
    'view' => 'View',

    'responses_heading' => 'Responses',
    'responses_hint' => 'Status of the parent poll. You can fill it in yourself if needed.',

    'delete_confirm' => 'Really delete excursion “:name”?',
    'delete_aria' => 'Delete excursion',

    'field_name' => 'Name of the excursion',
    'field_name_placeholder' => 'e.g. Zoo trip',
    'field_date' => 'Date',
    'next_friday' => 'Next Friday · :date',
    'field_deadline' => 'Respond by',
    'field_deadline_hint' => 'Parents can respond up to this day. Default: 3 days before the excursion.',
    'field_note' => 'Note (optional)',
    'field_note_placeholder' => 'e.g. bring a snack and sturdy shoes',

    'after_save_before' => 'After saving, ',
    'after_save_strong' => 'all parents',
    'after_save_after' => ' will be asked whether their child is joining.',

    'weekdays' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
];
