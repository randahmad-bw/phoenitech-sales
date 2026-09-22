<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company timezone
    |--------------------------------------------------------------------------
    | The application runs on UTC (config/app.php) and stores check_in_at /
    | check_out_at as ordinary UTC timestamps. Everything an employee *sees* or
    | is *measured against* — which day a check-in belongs to, what "today"
    | means, how a month is bounded — is resolved in this timezone instead.
    |
    | Without it, a 22:00 Damascus check-in would be filed under the next day.
    */
    'timezone' => env('ATTENDANCE_TIMEZONE', 'Asia/Damascus'),

    /*
    |--------------------------------------------------------------------------
    | Checking in on a non-working day
    |--------------------------------------------------------------------------
    | People do come in on their day off. When true, the check-in is accepted
    | and recorded; the day keeps its day_off / holiday / leave status and the
    | hours are visible in reports as work done outside the schedule.
    |
    | By management decision a day off earns no overtime, so these hours credit
    | nothing — they are recorded for the record, not for payroll.
    */
    'allow_check_in_on_day_off' => (bool) env('ATTENDANCE_ALLOW_DAY_OFF_CHECK_IN', true),

    /*
    |--------------------------------------------------------------------------
    | Auto check-out when closing the day
    |--------------------------------------------------------------------------
    | Someone who forgets to check out leaves an open row. The nightly
    | attendance:close-day command marks it `incomplete` and stops there: the
    | system does not invent a departure time. A human decides what happened
    | and corrects the record, which the audit trail then shows.
    |
    | Turning this on would make the command close such rows at the scheduled
    | end time instead. Off by default, on purpose.
    */
    'auto_checkout_on_close' => (bool) env('ATTENDANCE_AUTO_CHECKOUT', false),

    /*
    |--------------------------------------------------------------------------
    | First day of the displayed week
    |--------------------------------------------------------------------------
    | Presentation only: the order days appear in the schedule editor, and where
    | "this week" starts in the employee's history.
    |
    | This is NOT the working week. One employee works Saturday–Wednesday and
    | another Sunday–Thursday; that is expressed per employee by their schedule's
    | day rows (a weekday with no row is not a working day) and no global value
    | can override it.
    |
    | Carbon numbering: 0 = Sunday … 6 = Saturday.
    */
    'week_start' => (int) env('ATTENDANCE_WEEK_START', 6),

];
