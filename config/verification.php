<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Verification Enabled
    |--------------------------------------------------------------------------
    |
    | Toggle the file verification pipeline on or off. When disabled, no
    | change detection or verification jobs will be dispatched.
    |
    */

    'enabled' => env('VERIFICATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Docker Image
    |--------------------------------------------------------------------------
    |
    | The Docker image used to extract and inspect mod archives in an isolated,
    | network-less container. Published automatically via GitHub Actions.
    |
    */

    'docker_image' => env('VERIFICATION_DOCKER_IMAGE', 'ghcr.io/sp-tarkov/forge/verification:latest'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Files larger than this threshold (in bytes) will be skipped during
    | verification. Default: 500 MB.
    |
    */

    'max_file_size' => (int) env('VERIFICATION_MAX_FILE_SIZE', 500 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Allowed Ports
    |--------------------------------------------------------------------------
    |
    | Download URLs may only target these ports. The IP blocklist cannot protect
    | services that sit on public addresses, so this stops the downloader being
    | pointed at the database, Redis, SSH, or any other non-web port, whether it
    | is reached directly or through a redirect.
    |
    */

    'allowed_ports' => array_values(array_filter(array_map(
        intval(...),
        explode(',', (string) env('VERIFICATION_ALLOWED_PORTS', '80,443')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | The queue name used for verification jobs. Must match the Horizon
    | supervisor configuration in config/horizon.php.
    |
    */

    'queue' => env('VERIFICATION_QUEUE', 'verification'),

    'change_detection_queue' => env('VERIFICATION_CHANGE_DETECTION_QUEUE', 'verification-detection'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Extraction Ratio
    |--------------------------------------------------------------------------
    |
    | The maximum allowed ratio of extracted size to archive size. Archives
    | that exceed this ratio are flagged as potential zip bombs. A ratio of
    | 100 means the extracted content can be at most 100x the archive size.
    |
    */

    'max_extraction_ratio' => (int) env('VERIFICATION_MAX_EXTRACTION_RATIO', 100),

    /*
    |--------------------------------------------------------------------------
    | Maximum Extracted Size
    |--------------------------------------------------------------------------
    |
    | Absolute cap on total extracted content size (in bytes), regardless of
    | ratio. Default: 2 GB.
    |
    */

    'max_extracted_size' => (int) env('VERIFICATION_MAX_EXTRACTED_SIZE', 2 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Timeout values (in seconds) for each stage of the verification pipeline.
    |
    */

    'timeouts' => [
        'download' => (int) env('VERIFICATION_DOWNLOAD_TIMEOUT', 900),
        'container' => (int) env('VERIFICATION_CONTAINER_TIMEOUT', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale Thresholds
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) a verification result may remain in the Pending or
    | Running status before it is considered stale. Stale results no longer
    | block new dispatches and are marked as errored by the scheduled cleanup
    | job. Pending allows for queue backlog on the dedicated worker; Running
    | only needs to cover the job timeout plus retries.
    |
    */

    'stale' => [
        'pending_minutes' => (int) env('VERIFICATION_STALE_PENDING_MINUTES', 1440),
        'running_minutes' => (int) env('VERIFICATION_STALE_RUNNING_MINUTES', 60),
    ],

];
