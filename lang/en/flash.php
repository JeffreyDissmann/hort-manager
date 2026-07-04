<?php

declare(strict_types=1);

// Server-side flash messages (session 'status'), shown as a banner after an action.
return [
    'min_one_admin' => 'There must be at least one administrator.',
    'user_updated' => ':name updated.',
    'cannot_delete_self' => 'You cannot delete yourself.',
    'user_deleted' => ':name has been deleted.',
    'users_synced' => ':count users synced from Slack.',

    'absence_reported' => ':name reported as “:reason”.',
    'absence_cleared' => 'Absence for :name cancelled.',

    'child_created' => 'Child created. Now set the weekly schedule.',
    'schedule_saved' => 'Weekly schedule for :name saved.',
    'child_deleted' => ':name has been deleted.',

    'plan_updated' => 'Plan for :name updated.',
    'day_reset' => ':name: day reset to the standard.',

    'excursion_created' => 'Excursion “:name” created. The parents have been invited to respond.',
    'excursion_saved' => 'Excursion “:name” saved.',
    'excursion_deleted' => 'Excursion “:name” deleted.',
    'rsvp_saved' => 'Response for :name saved.',

    'program_saved' => 'Program saved.',
    'homework_defaults_saved' => 'Default homework times saved.',
];
