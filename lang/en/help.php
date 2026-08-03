<?php

declare(strict_types=1);

return [
    'title' => 'Help',
    'header' => 'Help & Guide',
    'to_login' => 'To sign in',
    'back_to_overview' => 'All topics',
    'on_this_page' => 'In this chapter',

    // Intro (hub)
    'intro_title' => 'Welcome to Hort-Manager 👋',
    'intro_text' => 'Hort-Manager helps parents and staff keep track together – above all of the most important thing: <strong>when and how each child goes home</strong>. You can do everything conveniently on your phone.',

    // Quick start (hub)
    'quick_start_title' => 'Ready in 4 steps',
    'steps' => [
        'Tap “Sign in with Slack” – your account is created automatically the first time.',
        'Add your child under “Children”.',
        'Enter the standard plan: when your child is picked up on which weekday.',
        'Done. From now on you see everything and get important info as a message in Slack.',
    ],

    'topics_title' => 'All topics',
    'topics_hint' => 'Pick whatever you need right now – each chapter stands on its own.',

    'audiences' => [
        'all' => 'For everyone',
        'parents' => 'For parents',
        'staff' => 'Staff only',
    ],

    'topics' => [
        'getting-started' => [
            'title' => 'Signing in & setting up',
            'teaser' => 'How to get in, put the app on your phone, and set language and appearance.',
            'audience' => 'all',
        ],
        'pickups' => [
            'title' => 'Pickups & the week',
            'teaser' => 'The standard plan, changes for single days, and going home with another child.',
            'audience' => 'all',
        ],
        'absences' => [
            'title' => 'When a child isn’t there',
            'teaser' => 'Reporting sick, “not coming” – and what “hortfrei” means.',
            'audience' => 'all',
        ],
        'holidays' => [
            'title' => 'Holidays & closures',
            'teaser' => 'When the Hort is shut, and how to sign your child up for holiday care.',
            'audience' => 'all',
        ],
        'excursions' => [
            'title' => 'Excursions',
            'teaser' => 'The poll: is your child coming along? One tap is enough.',
            'audience' => 'all',
        ],
        'slack' => [
            'title' => 'Slack & notifications',
            'teaser' => 'What Hort-Manager sends you, and how to simply write to it.',
            'audience' => 'all',
        ],
        'staff' => [
            'title' => 'For staff',
            'teaser' => 'Marking children off, the daily programme, setting up holidays – and who may do what.',
            'audience' => 'staff',
        ],
        'glossary' => [
            'title' => 'In brief',
            'teaser' => 'Every term of the app in one sentence. And where to get help.',
            'audience' => 'all',
        ],
    ],

    // ---------------------------------------------------------------- Sign in
    'getting-started' => [
        'login_title' => 'How do I sign in?',
        'login_text_1' => 'The easiest way is with <strong>“Sign in with Slack”</strong> – you do not need a password of your own for that. The prerequisite is that you are in the Hort’s Slack. Your account is created automatically the first time you sign in.',
        'login_text_2' => 'Alternatively you can sign in with <strong>email and password</strong>. Forgot your password? Via <strong>“Forgot your password?”</strong> on the sign-in page you get a link by email to set a new password.',

        'children_title' => 'Adding your child',
        'children_text' => 'Under <strong>Children</strong> you add your child and keep their standard plan up to date. You can also link the second parent there, so you both see and can change everything.',

        'install_title' => 'Install as an app',
        'install_text' => 'You can put Hort-Manager on your phone like a real app – it then starts full screen and can send you notifications.',
        'install_ios' => '<strong>iPhone (Safari):</strong> tap the share icon → “Add to Home Screen”.',
        'install_android' => '<strong>Android (Chrome):</strong> tap the “Install” banner at the top (or menu → “Install app”).',
        'install_enable' => 'Then tap <strong>🔔 Enable notifications</strong> in the menu at the top right and allow them.',
        'install_note' => 'Note: on the iPhone notifications only work if the app was added to the home screen first.',

        'appearance_title' => 'Appearance & language',
        'appearance_theme' => '<strong>Light or dark:</strong> under <strong>Profile → Appearance</strong> you choose between light, dark and “automatic” (follows your device). The choice applies per device.',
        'appearance_language' => '<strong>Language:</strong> under <strong>Profile → Language</strong> you can switch between German and English. German is the default.',
    ],

    // ---------------------------------------------------------------- Pickups
    'pickups' => [
        'intro' => 'Hort-Manager revolves around one question: <strong>when and how does your child go home today?</strong> The answer comes from the standard plan – and can be changed for individual days at any time.',

        'stammplan_title' => 'The standard plan: the fixed week',
        'stammplan_text' => 'The standard plan holds, for each weekday, when your child leaves and how: <strong>picked up</strong> or <strong>walks home alone</strong>. For “alone” you can qualify the time – <strong>by</strong>, <strong>at</strong> or <strong>from</strong> a given time. A weekday with no entry means <strong>hortfrei</strong>: your child isn’t at the Hort that day at all.',
        'stammplan_where' => 'Found under <strong>Children → pick a child</strong>. All standard plans together are under <strong>Standard</strong>.',

        'week_title' => 'The weekly plan: the actual week',
        'week_text' => 'The <strong>weekly plan</strong> shows the selected week with everything that belongs to it: lunch, activity, homework time and excursions. Use the arrows (or swipe) to move between weeks.',
        'week_edit' => 'Tap a day to change exactly that day – the time, how the child goes home, or a sick report. Changed days are marked <strong>“changed today”</strong>, and <strong>“Reset to standard plan”</strong> undoes the change.',

        'today_title' => 'Today: the pickup list',
        'today_text' => 'The <strong>Today</strong> page is the overview for one day: who is picked up when, who walks home alone, who is on an excursion. Staff mark each child off as they leave – and you get a message. A child with a birthday shows a 🎂.',

        'companion_title' => 'Going home with another child',
        'companion_intro' => 'Sometimes a child goes home with another one – to play, or because one family takes both. This is how it works:',
        'companion_points' => [
            'When changing a day, pick <strong>“Goes with another child”</strong> under “How” and then the child. The pickup time is taken from that child automatically – if their time changes, so does yours.',
            'If the other child is <strong>picked up</strong>, everything is settled straight away – an adult is there after all.',
            'If the other child <strong>walks home alone</strong>, their family has to agree first. Until then everyone else only sees the normal pickup time – the arrangement appears once they say yes.',
            'On “Today” and in the weekly plan you see a <strong>“Going home together”</strong> summary – with the state of each request, and a way to confirm when a child wants to go with yours.',
        ],

        'late_title' => 'Short-notice changes',
        'late_text' => 'Changes for <strong>today</strong> are always possible. If they happen after the agreed time (usually 12:00), the staff get a short message so the change really reaches them. The hint appears before you save – it isn’t an error.',
    ],

    // ---------------------------------------------------------------- Absences
    'absences' => [
        'intro' => 'There are <strong>three different ways</strong> a child isn’t at the Hort. They look similar but mean different things:',

        'sick_title' => '1. Sick or “not coming”',
        'sick_text' => 'Your child is ill or isn’t coming on a day for another reason. Tap <strong>“Sick”</strong> or <strong>“Not coming”</strong> – on the “Today” page or on the day in the weekly plan – and give a short reason (e.g. “a cold” or “family visit”).',
        'sick_undo' => 'The child is then off the pickup list for that day. Clear the report and they return to their normal plan. You can also report several days at once.',
        'sick_slack' => 'Even quicker in Slack: just write “my child is ill today” to Hort-Manager (see <strong>Slack & notifications</strong>).',

        'hortfrei_title' => '2. Hortfrei',
        'hortfrei_text' => '<strong>Hortfrei</strong> means: on this weekday your child is generally not at the Hort – that’s what the standard plan says. It isn’t an absence and doesn’t have to be reported anywhere.',
        'hortfrei_note' => 'If your child should stay <em>as an exception</em> on such a day, tap their name under “hortfrei” on Today or in the weekly plan and enter a pickup time.',

        'closed_title' => '3. Closure',
        'closed_text' => 'On a <strong>closure</strong> the Hort itself is shut – holidays, a bridge day or staff training. Then there is no Hort day for anybody: those days are grey in the weekly plan, there is nothing to enter, and nobody has to report anything.',
        'closed_link' => 'All closures are listed under <strong>Closures</strong> in the menu – along with when holiday care is offered instead.',
    ],

    // ---------------------------------------------------------------- Holidays
    'holidays' => [
        'intro' => 'In the holidays there are two possibilities: the Hort is <strong>closed</strong>, or there is <strong>holiday care</strong> you sign your child up for day by day. Closures live in the menu at the top right; signing up happens under <strong>Excursions &amp; holidays</strong>.',

        'closed_title' => 'Closures',
        'closed_text' => 'Under <strong>Closures</strong> you find every period the Hort is shut – with a name and dates, so you can plan your own holidays around them. In the weekly plan those days are greyed out and locked.',

        'care_title' => 'Holiday care: signing up day by day',
        'care_text' => 'During <strong>holiday care</strong> the Hort is open – but only for the children who signed up. There is no school in the holidays, so the normal standard plan doesn’t apply: you say for <strong>each single day</strong> whether your child is coming.',
        'care_points' => [
            'Open <strong>Excursions &amp; holidays</strong>. Every offered day is listed with its <strong>care window</strong> (e.g. 08:30–16:00) – right next to the trips waiting for an answer.',
            'Tick the days your child is coming and save. Picking <strong>no day at all</strong> is just as valid an answer as picking every day.',
            'Watch the <strong>registration deadline</strong>. After it you can’t change anything yourself; ask the staff, they can still sign your child up.',
            'You get a message when holiday care opens, and a reminder on the deadline day if you haven’t answered yet.',
        ],

        'care_day_title' => 'Such a day runs like any other',
        'care_day_text' => 'Once your child is signed up the day is an ordinary Hort day: they’re on the pickup list, get picked up or walk home alone, can go with another child, and you get the usual message when they’ve left.',
        'care_day_points' => [
            'The pickup time starts out as the <strong>end of the care window</strong>. You can change it in the weekly plan like on any other day.',
            'If a signed-up child falls ill, report them sick as usual – <strong>their place is kept</strong>.',
            'There is no homework in the holidays; lunch and activity are shown with the day as usual.',
            'If a closure falls on the same day, the closure wins – the Hort is shut.',
        ],
    ],

    // ---------------------------------------------------------------- Excursions
    'excursions' => [
        'intro' => 'When an excursion is coming up you’re asked whether your child is joining. Excursions are planned by the staff.',
        'points' => [
            'You get a message with all the details – destination, date, departure and return – and two buttons: <strong>yes</strong> or <strong>no</strong>.',
            'You can answer <strong>right in Slack</strong> or in the app under <strong>Excursions</strong>. Both are the same thing; both parents see the answer.',
            'Until the <strong>deadline</strong> you can change your answer at any time. If you haven’t answered, Hort-Manager reminds you.',
            'On the day of the trip your child appears on the pickup list with a 🚌 – they’re picked up as normal afterwards.',
        ],
    ],

    // ---------------------------------------------------------------- Slack
    'slack' => [
        'slack_title' => 'What happens in Slack?',
        'slack_intro' => 'Hort-Manager is connected to the Hort’s Slack so you don’t miss anything:',
        'slack_points' => [
            'You get a short message as soon as your child has been picked up or walked home.',
            'For a new excursion or a new holiday care period Hort-Manager sends you all the details – you can answer right in Slack.',
            'You can also simply write to Hort-Manager (see below) or type “/hort” to jump into the app.',
            'The “Hort-Manager” app in your Slack sidebar brings you here any time.',
        ],

        'assistant_title' => 'Just write to it',
        'assistant_text' => '<strong>Write to Hort-Manager directly in Slack.</strong> It understands ordinary sentences – for example:',
        'assistant_examples' => [
            'My child is ill today.',
            'Lena will only be picked up at 16:30 tomorrow.',
            'Tom walks home alone from Monday.',
            'Is Lena coming to the zoo trip? Yes.',
            'When does Tom leave today?',
        ],
        'assistant_note' => 'This works as a direct message to “Hort-Manager” in Slack or with “/hort …”. It only handles your own children and confirms briefly what it entered. Check the reply – if something was misunderstood, just write the correct detail after it.',

        'notifications_title' => 'Which notifications do I get?',
        'notifications_intro' => 'Hort-Manager gets in touch when something important happens – as a <strong>push notification</strong> on your device and, if your account is linked to Slack, as a <strong>Slack message</strong> too. Both say the same; one channel is enough.',
        'notifications_points' => [
            '<strong>Child picked up / walked home:</strong> as soon as the staff mark your child off, you hear about it.',
            '<strong>New excursion:</strong> you’re invited to answer – with a reminder if you haven’t yet.',
            '<strong>Holiday care:</strong> you hear when registration opens, and are reminded on the deadline.',
            '<strong>A child wants to go with yours:</strong> if your child walks home alone and another is to come along, their family asks you – with “yes/no” in Slack or in the app.',
            '<strong>Answer about going along:</strong> if you asked whether your child may go with another, you hear as soon as the other family has answered.',
            '<strong>Weekly overview:</strong> on Mondays you get the coming week at a glance – programme, excursions and your children’s plan.',
        ],
        'notifications_settings' => 'What you want to receive is set under <strong>Profile → Notifications</strong> – separately for Slack and push. You can answer anywhere; both sides stay in sync automatically.',
    ],

    // ---------------------------------------------------------------- Staff
    'staff' => [
        'intro' => 'This chapter is for the Hort team. Parents are welcome to read along – it explains what happens behind the scenes.',

        'board_title' => 'Marking off on “Today”',
        'board_text' => 'The <strong>Today</strong> page holds the day’s pickup list, sorted by time. Tap a child as they leave – their family gets a message straight away. You can also change a child’s pickup time here at short notice.',

        'program_title' => 'Daily programme',
        'program_text' => 'Under <strong>Programme</strong> you enter lunch, activity and homework times for the week – “no homework” is possible too. Parents see it in the weekly plan and on “Today”.',
        'program_reminder' => 'If a lunch is still missing for the week on Monday, Hort-Manager reminds you shortly before the parents’ weekly overview goes out. The same page holds the time for <strong>late changes</strong> and for sending the weekly overview.',

        'holidays_title' => 'Setting up closures & holiday care',
        'holidays_points' => [
            'Under <strong>Closures</strong> you create both: a closed period or <strong>holiday care</strong>. A single day is simply a one-day period.',
            'For holiday care every weekday of the period is offered with the default care window. You can adjust the times per day, take a day out entirely – and offer it again later.',
            'The <strong>registration deadline</strong> belongs to the period. Until then parents sign up themselves; after it only you can.',
            'A closure beats holiday care: if both fall on the same day, the Hort is shut. An excursion inside the period has to be moved or cancelled first.',
        ],

        'roles_title' => 'Who may do what?',
        'role_parents' => '<strong>Parents</strong> see everything, look after their own children and answer excursions and holiday care.',
        'role_staff' => '<strong>Staff</strong> mark departures off, plan excursions, the programme and the holidays – and may enter things for any child.',
        'role_admins' => '<strong>Admins</strong> additionally manage the users and assign the roles.',
    ],

    // ---------------------------------------------------------------- Glossary
    'glossary' => [
        'intro' => 'The terms used in the app – one sentence each.',
        'terms' => [
            'stammplan' => [
                'term' => 'Standard plan (Stammplan)',
                'def' => 'A child’s fixed, weekly repeating pickup times – the basis for the weekly plan.',
            ],
            'pickup_plan' => [
                'term' => 'Weekly plan',
                'def' => 'The concrete plan for one particular week. It comes from the standard plan but can be adjusted per day.',
            ],
            'departure' => [
                'term' => 'picked up / alone / with a child',
                'def' => 'The three ways a child gets home: picked up, walking home alone, or together with another child.',
            ],
            'companion' => [
                'term' => 'Going with another child',
                'def' => 'A child goes home with another one and takes over their pickup time. If the other child walks home alone, their family has to agree.',
            ],
            'absence' => [
                'term' => 'Sick / Not coming',
                'def' => 'A child is reported ill or away for a day – they are then off the pickup list that day.',
            ],
            'hortfrei' => [
                'term' => 'Hortfrei',
                'def' => 'A weekday on which a child isn’t at the Hort at all according to their standard plan. Not an absence, nothing to report.',
            ],
            'closure' => [
                'term' => 'Closure (Schließzeit)',
                'def' => 'A period during which the Hort is closed for everyone – holidays, a bridge day, staff training.',
            ],
            'care' => [
                'term' => 'Holiday care (Ferienbetreuung)',
                'def' => 'Care during the holidays that parents sign their child up for day by day. Only registered children are there.',
            ],
        ],

        'questions_title' => 'Any questions?',
        'questions_text' => 'For questions or problems with the app, get in touch with the developer <strong>Jeffrey Dissmann</strong>:',
    ],
];
