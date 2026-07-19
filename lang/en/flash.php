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
    'companion_answered' => 'Answer saved for :name.',

    'excursion_created' => 'Excursion “:name” created. The parents have been invited to respond.',
    'excursion_saved' => 'Excursion “:name” saved.',
    'excursion_deleted' => 'Excursion “:name” deleted.',
    'rsvp_saved' => 'Response for :name saved.',

    'program_saved' => 'Program saved.',
    'homework_defaults_saved' => 'Default homework times saved.',

    'account_created' => 'Account “:name” created.',
    'account_updated' => 'Account “:name” saved.',
    'account_deleted' => 'Account “:name” deleted.',
    'account_has_bookings' => 'Account “:name” has bookings and cannot be deleted. Deactivate it instead.',

    'category_created' => 'Category created.',
    'category_updated' => 'Category saved.',
    'category_deleted' => 'Category “:name” deleted.',
    'category_has_bookings' => 'Category “:name” (or a subcategory) has bookings and cannot be deleted. Deactivate it instead.',

    'booking_created' => 'Booking created.',
    'booking_updated' => 'Booking saved.',
    'booking_deleted' => 'Booking deleted.',
    'transfer_created' => 'Transfer created.',
];
