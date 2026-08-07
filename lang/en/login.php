<?php

declare(strict_types=1);

return [
    // Login
    'title' => 'Sign in',
    'sign_in_with_slack' => 'Sign in with Slack',
    'or_with_email' => 'or with e-mail',
    'email' => 'E-mail address',
    'password' => 'Password',
    'remember_me' => 'Stay signed in',
    'forgot_password' => 'Forgot your password?',
    'sign_in' => 'Sign in',
    'new_here' => 'New here?',
    'how_it_works' => 'How the Hort-Manager works',

    // First sign-in — mostly new parents after the holidays. There is no
    // self-registration: either Slack creates the account, or it already exists.
    'first_time_here' => 'Here for the first time?',
    'first_slack_title' => '1. Sign in with Slack',
    'first_slack_text' => 'The easiest way: tap <strong>“Sign in with Slack”</strong> above. You don’t need a password of your own — your account is created automatically the first time. It does require you to be in the Hort’s Slack.',
    'first_slack_fallback' => '<strong>Not working?</strong> If you are not (yet) in the Hort’s Slack, or the Slack sign-in shows an error, just take way 2 instead — it works just as well.',
    'first_password_title' => '2. Without Slack: set your first password',
    'first_password_text' => 'Go to <a href=":url"><strong>“Forgot your password?”</strong></a> and enter your e-mail address — the one the Hort has for you. The link in that e-mail lets you set your first password; after that you sign in with e-mail and password as usual.',
    'first_stuck' => 'No e-mail arriving? Then there is no account for that address yet — just ask the Hort team.',

    // Forgot password
    'forgot_title' => 'Forgot password',
    'forgot_intro' => 'Forgot your password? No problem. Just let us know your e-mail address and we will send you a link that lets you choose a new password.',
    'send_reset_link' => 'Send password reset link',

    // Reset password
    'reset_title' => 'Reset password',
    'confirm_password' => 'Confirm password',
    'reset_password' => 'Reset password',

    // Confirm password
    'confirm_title' => 'Confirm password',
    'confirm_intro' => 'This is a secure area. Please confirm your password before continuing.',

    // Verify email
    'verify_title' => 'E-mail verification',
    'verify_intro' => 'Thanks for signing up! Please confirm your e-mail address using the link we just sent you. If you did not receive the e-mail, we will gladly send you another one.',
    'verify_link_sent' => 'A new verification link has been sent to your e-mail address.',
    'resend_verification' => 'Resend verification e-mail',
    'log_out' => 'Log out',
];
