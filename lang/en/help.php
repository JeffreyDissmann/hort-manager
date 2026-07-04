<?php

declare(strict_types=1);

return [
    'title' => 'Help',
    'header' => 'Help & Guide',
    'to_login' => 'To sign in',

    // Intro
    'intro_title' => 'Welcome to Hort-Manager 👋',
    'intro_text' => 'Hort-Manager helps parents and staff keep track together – above all of the most important thing: <strong>when and how each child goes home</strong>. You can do everything conveniently on your phone.',

    // Quick start
    'quick_start_title' => 'Ready in 4 steps',
    'steps' => [
        'Tap “Sign in with Slack” – your account is created automatically the first time.',
        'Add your child under “Children”.',
        'Enter the standard plan: when your child is picked up on which weekday.',
        'Done. From now on you see everything and get important info as a message in Slack.',
    ],

    // Login
    'login_title' => 'How do I sign in?',
    'login_text_1' => 'The easiest way is with <strong>“Sign in with Slack”</strong> – you do not need a password of your own for that. The prerequisite is that you are in the Hort’s Slack. Your account is created automatically the first time you sign in.',
    'login_text_2' => 'Alternatively you can sign in with <strong>email and password</strong>. Forgot your password? Via <strong>“Forgot your password?”</strong> on the sign-in page you get a link by email to set a new password.',

    // Areas
    'areas_title' => 'What can I do where?',
    'areas' => [
        'today' => [
            'title' => 'Today',
            'audience' => 'For everyone',
            'text' => 'The overview for today: who is picked up when, who goes home alone, who is on an excursion. Staff check off each child as soon as it leaves.',
        ],
        'pickup_plan' => [
            'title' => 'Pickup plan',
            'audience' => 'For everyone',
            'text' => 'At the top you see the whole week at a glance – all children with their pickup times and the times for lunch, activity, homework and excursions. If you need a different time on one day as an exception, you can adjust exactly that day or report your child sick.',
        ],
        'excursions' => [
            'title' => 'Excursions',
            'audience' => 'Parents respond',
            'text' => 'When an excursion is coming up, you are asked whether your child is joining. A single click on Yes or No is enough – in the app or directly in Slack. Staff plan the excursions.',
        ],
        'children' => [
            'title' => 'Children',
            'audience' => 'Parents & staff',
            'text' => 'Here you add your child and maintain its standard plan. You can also link the second parent so that you both see and can change everything.',
        ],
        'program' => [
            'title' => 'Program',
            'audience' => 'Staff only',
            'text' => 'Staff enter lunch, activity and the homework times for the week – “no homework” is also possible. Parents can see it too; the homework time also appears on “Today”.',
        ],
    ],

    // Slack
    'slack_title' => 'What happens in Slack?',
    'slack_intro' => 'Hort-Manager is connected to the Hort’s Slack so you don’t miss anything:',
    'slack_points' => [
        'You get a short message as soon as your child has been picked up or has left on their own.',
        'For a new excursion, Hort-Manager sends you all the info with Yes/No buttons – you can respond right in Slack.',
        'You can also simply write to Hort-Manager (see below) or type “/hort” to jump into the app.',
        'Via the “Hort-Manager” app in your Slack sidebar you can get here anytime.',
    ],

    // Assistant & sick reports
    'assistant_title' => 'Report sick & quick changes',
    'assistant_text_1' => 'Is your child sick or not coming on a certain day? Tap <strong>“Sick”</strong> or <strong>“Absent”</strong> – on the “Today” page or on the respective day in the pickup plan. That’s also how you enter a different pickup time at short notice.',
    'assistant_text_2' => 'Even easier: <strong>Just write it to Hort-Manager directly in Slack.</strong> It understands plain sentences – for example:',
    'assistant_examples' => [
        'My child is sick today.',
        'Lena is being picked up only at 16:30 tomorrow.',
        'Tom goes home alone from Monday on.',
        'Is Lena joining the zoo excursion? Yes.',
        'When does Tom leave today?',
    ],
    'assistant_note' => 'This works via direct message to “Hort-Manager” in Slack or with “/hort …”. It only handles your own children and briefly confirms what it has entered. Check the reply – if there is a misunderstanding, just write the correct detail again.',

    // Install as app
    'install_title' => 'Install as an app & notifications',
    'install_text' => 'You can put Hort-Manager on your phone like a real app – then it starts in full screen and can send you notifications (e.g. “Child was picked up” or a reminder about an excursion).',
    'install_ios' => '<strong>iPhone (Safari):</strong> tap the share icon → “Add to Home Screen”.',
    'install_android' => '<strong>Android (Chrome):</strong> tap the “Install” banner at the top (or menu → “Install app”).',
    'install_enable' => 'Then tap <strong>🔔 Notifications on</strong> in the menu at the top right and allow them.',
    'install_note' => 'Note: On the iPhone notifications only work if the app was added to the home screen beforehand.',

    // Roles
    'roles_title' => 'Who is allowed to do what?',
    'role_parents' => '<strong>Parents</strong> see everything, maintain their own children and respond to excursions.',
    'role_staff' => '<strong>Staff</strong> check off pickups and plan excursions and the program.',
    'role_admins' => '<strong>Admins</strong> additionally manage the users and assign the roles.',

    // Glossary
    'glossary_title' => 'Briefly explained',
    'glossary' => [
        'stammplan' => [
            'term' => 'Standard plan',
            'def' => 'The fixed, weekly-recurring pickup times of a child – the basis for the pickup plan.',
        ],
        'pickup_plan' => [
            'term' => 'Pickup plan',
            'def' => 'The concrete plan for a specific week. It comes from the standard plan but can be adjusted per day.',
        ],
        'departure' => [
            'term' => 'picked up / left alone',
            'def' => 'The two ways a child goes home: picked up by someone or gone home alone.',
        ],
        'absence' => [
            'term' => 'Sick report / absence',
            'def' => 'A child is reported sick or absent for a day – then it is not on the pickup list that day.',
        ],
    ],

    // Questions
    'questions_title' => 'Any questions?',
    'questions_text' => 'If you have questions or problems with the app, contact the developer <strong>Jeffrey Dissmann</strong>:',
];
