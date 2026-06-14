<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Log Storage Path
    |--------------------------------------------------------------------------
    |
    | The base directory where structured log files will be stored.
    | Logs are organized by date and hour: {path}/{YYYY-MM-DD}/{HH}.jsonl
    |
    */
    'path' => env('LOGGER_PATH', storage_path('logs/structured')),

    /*
    |--------------------------------------------------------------------------
    | Buffer Size
    |--------------------------------------------------------------------------
    |
    | Number of log entries to accumulate in memory before writing to disk.
    | Set to null or 0 to disable buffering.
    | Higher values improve write performance but use more memory.
    |
    */
    'buffer_size' => env('LOGGER_BUFFER_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Log Retention Days
    |--------------------------------------------------------------------------
    |
    | Number of days to keep log files before automatic cleanup.
    | Logs older than this will be deleted during the cleanup process.
    |
    */
    'retention_days' => (int) env('LOGGER_RETENTION_DAYS', 30),
];
