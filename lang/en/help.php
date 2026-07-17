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
            'text' => 'The overview for a day: who is picked up when, who goes home alone, who is on an excursion. Staff check off each child as soon as it leaves. If a child has a birthday, you’ll see it here with a 🎂. Use the arrows or tap the date to view any other day too — e.g. what’s planned for Friday, with lunch and activity.',
        ],
        'pickup_plan' => [
            'title' => 'Weekly plan',
            'audience' => 'For everyone',
            'text' => 'The whole week at a glance – with lunch, activity, homework and excursions. Tap a day to adjust it: time, whether your child is picked up, goes home alone (also “until / exactly at / from” a time) or goes home with another child – or report it sick or “not coming”.',
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
    'assistant_text_1' => 'Is your child sick or not coming on a certain day? Tap <strong>“Sick”</strong> or <strong>“Not coming”</strong> – on the “Today” page or on the respective day in the weekly plan – and add a short reason (e.g. “a cold” or “family visit”). That’s also how you enter a different pickup time at short notice.',
    'assistant_text_2' => 'Even easier: <strong>Just write it to Hort-Manager directly in Slack.</strong> It understands plain sentences – for example:',
    'assistant_examples' => [
        'My child is sick today.',
        'Lena is being picked up only at 16:30 tomorrow.',
        'Tom goes home alone from Monday on.',
        'Is Lena joining the zoo excursion? Yes.',
        'When does Tom leave today?',
    ],
    'assistant_note' => 'This works via direct message to “Hort-Manager” in Slack or with “/hort …”. It only handles your own children and briefly confirms what it has entered. Check the reply – if there is a misunderstanding, just write the correct detail again.',

    // Go home with another child
    'companion_title' => 'Going home with another child',
    'companion_intro' => 'Sometimes a child goes home with another one – to play, or because one family takes both. Here’s how it works:',
    'companion_points' => [
        'In the weekly plan, pick <strong>“Goes home with another child”</strong> under “Type”, then choose the child. The pickup time is taken from that child automatically – if their time changes, yours changes with it.',
        'If the other child is <strong>picked up</strong>, everything is settled right away – an adult is there anyway.',
        'If the other child goes home <strong>alone</strong>, their family has to agree first. Until then everyone else just sees the normal pickup time – the “going home with” only appears after the yes.',
        'At the top of “Today” and in the weekly plan you see a “Going home with others” overview – with the status of the agreement, and to confirm when a child wants to go home with yours.',
    ],

    // Notifications
    'notifications_title' => 'Which notifications do I get?',
    'notifications_intro' => 'Hort-Manager lets you know when something important happens – as a <strong>push notification</strong> on your device and, if your account is connected to Slack, additionally as a <strong>Slack message</strong>. Both show the same thing; one channel is enough.',
    'notifications_points' => [
        '<strong>Child picked up / left alone:</strong> as soon as the Hort team checks your child off, you’re notified.',
        '<strong>New excursion:</strong> you’re invited to respond – with a reminder if you haven’t answered yet.',
        '<strong>A child wants to go home with yours:</strong> if your child goes home alone and another one wants to come along, their family asks you for permission – right there with “Yes/No” in Slack or in the app.',
        '<strong>Answer about going along:</strong> if you asked whether your child may go home with another one, you’ll find out as soon as the other family accepts or declines.',
        '<strong>Going along not possible:</strong> if the other child is reported sick or absent, we let you know so you can re-plan the pickup.',
    ],
    'notifications_note' => 'You can respond anywhere – in Slack or in the app; both sides automatically stay on the same page.',

    // Install as app
    'install_title' => 'Install as an app',
    'install_text' => 'You can put Hort-Manager on your phone like a real app – then it starts in full screen and can send you notifications.',
    'install_ios' => '<strong>iPhone (Safari):</strong> tap the share icon → “Add to Home Screen”.',
    'install_android' => '<strong>Android (Chrome):</strong> tap the “Install” banner at the top (or menu → “Install app”).',
    'install_enable' => 'Then tap <strong>🔔 Notifications on</strong> in the menu at the top right and allow them.',
    'install_note' => 'Note: On the iPhone notifications only work if the app was added to the home screen beforehand.',

    // Appearance & language
    'appearance_title' => 'Appearance & language',
    'appearance_theme' => '<strong>Light or dark:</strong> under <strong>Profile → Appearance</strong> you can choose Light, Dark or “Automatic” (follows your device setting). The choice is per device.',
    'appearance_language' => '<strong>Language:</strong> under <strong>Profile → Language</strong> you can switch between German and English. German is the default.',

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
            'term' => 'Weekly plan',
            'def' => 'The concrete plan for a specific week. It comes from the standard plan but can be adjusted per day.',
        ],
        'departure' => [
            'term' => 'picked up / alone / with a child',
            'def' => 'The three ways a child goes home: picked up, home alone, or together with another child.',
        ],
        'companion' => [
            'term' => 'Going home with another child',
            'def' => 'A child goes home with another one and takes over their pickup time. If the other child goes alone, their family must agree.',
        ],
        'absence' => [
            'term' => 'Sick / Not coming',
            'def' => 'A child is reported sick or absent for a day – then it is not on the pickup list that day.',
        ],
    ],

    // Questions
    'questions_title' => 'Any questions?',
    'questions_text' => 'If you have questions or problems with the app, contact the developer <strong>Jeffrey Dissmann</strong>:',
];
