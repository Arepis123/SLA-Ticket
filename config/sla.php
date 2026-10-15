<?php

return [

    /*
    |--------------------------------------------------------------------------
    | At-risk threshold
    |--------------------------------------------------------------------------
    |
    | Percentage of the SLA window that may elapse before a ticket is flagged
    | as "at risk" and staff are warned.
    |
    */

    'at_risk_threshold' => (int) env('SLA_AT_RISK_THRESHOLD', 75),

    /*
    |--------------------------------------------------------------------------
    | Auto close
    |--------------------------------------------------------------------------
    |
    | Resolved tickets the client has not confirmed or reopened are closed
    | automatically after this many days. Set to 0 to disable.
    |
    */

    'auto_close_days' => (int) env('SLA_AUTO_CLOSE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Business hours timezone
    |--------------------------------------------------------------------------
    |
    | The timezone your office hours and holidays are written in, for example
    | "Asia/Kuala_Lumpur". Leave empty to use the application timezone.
    |
    */

    'timezone' => env('SLA_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Cron token
    |--------------------------------------------------------------------------
    |
    | Shared secret for public/cron.php, which runs the scheduled SLA work on
    | hosts that cannot run `artisan schedule:run` from cron. Leave this empty
    | to refuse all HTTP access to that script.
    |
    */

    'cron_token' => env('SLA_CRON_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Ticket reference prefix
    |--------------------------------------------------------------------------
    */

    'reference_prefix' => env('SLA_REFERENCE_PREFIX', 'TKT'),

    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    |
    | Name and logo (path inside the public folder) printed in the header of
    | exported PDF reports.
    |
    */

    'company' => [
        'name' => env('SLA_COMPANY_NAME', 'Enigmatic Solutions'),
        'logo' => env('SLA_COMPANY_LOGO', 'images/enigmatic-solutions-logo.png'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('SLA_ATTACHMENT_DISK', 'local'),
        'max_kb' => 10240,
        'mimes' => 'jpg,jpeg,png,gif,webp,pdf,txt,log,csv,xlsx,xls,docx,doc,zip,mp4,mov',
    ],

];
