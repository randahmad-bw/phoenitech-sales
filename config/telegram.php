<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Off unless a token and a chat are configured, and off by default
    | everywhere else — tests, local work and any environment that has not been
    | given a bot must never reach out to Telegram.
    */
    'enabled' => (bool) env('TELEGRAM_NOTIFICATIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Bot credentials
    |--------------------------------------------------------------------------
    | `token` comes from @BotFather. `chat_id` is the management group the bot
    | posts into — a group id is negative (e.g. -1001234567890), a private chat
    | is positive. `php artisan telegram:check --updates` prints the id of any
    | chat the bot has recently seen.
    */
    'token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Reaching api.telegram.org
    |--------------------------------------------------------------------------
    | Some networks cannot open a connection to Telegram at all. `proxy` is
    | passed straight to Guzzle (e.g. http://127.0.0.1:1080 or a socks5:// URL)
    | and is the answer when `telegram:check` reports a connection error.
    |
    | The timeout is deliberately short: a message nobody is waiting for is not
    | worth holding a PHP worker open for.
    */
    'proxy' => env('TELEGRAM_PROXY'),
    'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    | False — the default — sends the message after the HTTP response has
    | already gone back to the employee, in the same process. It needs no queue
    | worker, which is why it is the default.
    |
    | Turn it on once a worker is running (`php artisan queue:work`): the same
    | job then goes onto the queue, where a failed send is retried instead of
    | being logged and forgotten.
    */
    'queue' => (bool) env('TELEGRAM_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | What gets announced
    |--------------------------------------------------------------------------
    | Per-event switches, so the group can be quietened down without a code
    | change. Check-in fires for every employee every day — if that turns out
    | to be noise, this is the line to turn off.
    */
    'events' => [
        'attendance.check_in' => (bool) env('TELEGRAM_NOTIFY_CHECK_IN', true),
        'attendance.check_out' => (bool) env('TELEGRAM_NOTIFY_CHECK_OUT', true),
        'leave.requested' => (bool) env('TELEGRAM_NOTIFY_LEAVE_REQUEST', true),
    ],

];
