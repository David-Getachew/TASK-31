<?php

return [

    'moderation' => [
        'edit_window_minutes' => (int) env('CL_EDIT_WINDOW_MINUTES', 15),
    ],

    'orders' => [
        'auto_close_minutes' => (int) env('CL_ORDER_AUTO_CLOSE_MINUTES', 30),
    ],

    'billing' => [
        'penalty_rate_bps'       => (int) env('CL_PENALTY_RATE_BPS', 500),
        'penalty_grace_days'     => (int) env('CL_PENALTY_GRACE_DAYS', 10),
        'penalty_bill_due_days'  => (int) env('CL_PENALTY_BILL_DUE_DAYS', 30),
        'recurring_day_of_month' => (int) env('CL_RECURRING_DAY_OF_MONTH', 1),
        'recurring_hour'         => (int) env('CL_RECURRING_HOUR', 2),
    ],

    'notifications' => [
        'fanout_batch_size' => (int) env('CL_NOTIFICATION_FANOUT_BATCH_SIZE', 50),
    ],

    'receipts' => [
        'number_prefix' => env('CL_RECEIPT_NUMBER_PREFIX', 'RC'),
    ],

    'idempotency' => [
        'ttl_hours' => (int) env('CL_IDEMPOTENCY_TTL_HOURS', 24),
    ],

    'backups' => [
        'retention_days' => (int) env('CL_BACKUP_RETENTION_DAYS', 30),
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
        // Optional staging override: when set to an existing file, that file is used
        // as the source-of-truth artifact to encrypt. When null or missing, the backup
        // pipeline generates a fresh DB export via DatabaseExportService before encrypting.
        'source_path'    => env('CL_BACKUP_SOURCE_PATH'),
        'target_dir'     => env('CL_BACKUP_TARGET_DIR', storage_path('app/backups')),
    ],

    'diagnostics' => [
        'encryption_key' => env('DIAGNOSTIC_ENCRYPTION_KEY'),
    ],

    'auth' => [
        'password_min_length'        => (int) env('CL_PASSWORD_MIN_LENGTH', 10),
        'login_lock_threshold'       => (int) env('CL_LOGIN_LOCK_THRESHOLD', 5),
        'login_lock_window_minutes'  => (int) env('CL_LOGIN_LOCK_WINDOW_MINUTES', 15),
        'login_lock_duration_minutes' => (int) env('CL_LOGIN_LOCK_DURATION_MINUTES', 15),
        'token_ttl_minutes'          => (int) env('CL_TOKEN_TTL_MINUTES', 720),
    ],

    'observability' => [
        'circuit_trip_bps'      => (int) env('CL_CIRCUIT_TRIP_BPS', 200),
        'circuit_reset_bps'     => (int) env('CL_CIRCUIT_RESET_BPS', 100),
        'circuit_window_seconds' => (int) env('CL_CIRCUIT_WINDOW_SECONDS', 300),
    ],
];
