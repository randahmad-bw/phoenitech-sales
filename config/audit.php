<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Turning this off silences every audit write (model events, auth events,
    | explicit calls). Reads of existing rows are unaffected.
    */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    | Default age, in days, used by `php artisan audit:prune`. The scheduled
    | nightly prune uses this value. Set to 0 to keep entries forever.
    */
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Globally excluded attributes
    |--------------------------------------------------------------------------
    | Never written to old_values/new_values for ANY model. Secrets belong here;
    | timestamps are noise. Per-model exclusions go in the model's $auditExclude.
    */
    'excluded_attributes' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'created_at',
        'updated_at',
        'deleted_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Value size cap
    |--------------------------------------------------------------------------
    | Long text fields (notes, JSON plans) are truncated to this many characters
    | so one row cannot bloat the table.
    */
    'max_value_length' => 2000,

];
