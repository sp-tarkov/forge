<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Verification
    |--------------------------------------------------------------------------
    |
    | Toggle automatic file verification on or off. When disabled, uploads no
    | longer dispatch verification jobs and the scheduled change detection and
    | cleanup jobs are not registered. Manual verification from the admin
    | dashboard is always available.
    |
    */

    'auto_enabled' => env('AUTO_VERIFICATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Minimum SPT Version
    |--------------------------------------------------------------------------
    |
    | Mod versions are only verified when their SPT constraint can match this version or newer. Versions for older
    | SPT releases are skipped everywhere a verification would otherwise be dispatched.
    |
    */

    'min_spt_version' => env('VERIFICATION_MIN_SPT_VERSION', '4.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Docker Image
    |--------------------------------------------------------------------------
    |
    | The Docker image used to extract and inspect mod archives in an isolated,
    | network-less container. Published automatically via GitHub Actions.
    |
    */

    'docker_image' => env('VERIFICATION_DOCKER_IMAGE', 'ghcr.io/sp-tarkov/forge/verification:main'),

    /*
    |--------------------------------------------------------------------------
    | Local Image Build
    |--------------------------------------------------------------------------
    |
    | When enabled, the verification job builds the image from the local
    | docker/verification/ Dockerfile before each run and uses it instead of
    | pulling the published registry image. Defaults to on in the local
    | environment, where the registry image is unavailable.
    |
    */

    'build_local_image' => env('VERIFICATION_BUILD_LOCAL_IMAGE', env('APP_ENV') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Container Resource & Pull Limits
    |--------------------------------------------------------------------------
    |
    | Hard limits applied to the extraction container. The process/thread cap is
    | fork-bomb protection; it must stay well above the handful of threads the
    | .NET runtime spawns, so the default is deliberately generous.
    |
    */

    'container' => [
        'pids_limit' => (int) env('VERIFICATION_CONTAINER_PIDS_LIMIT', 256),
        'pull_policy' => env('VERIFICATION_CONTAINER_PULL_POLICY', 'always'),
        'memory' => env('VERIFICATION_CONTAINER_MEMORY', '3g'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Archives larger than this threshold (in bytes) are rejected. The limit is
    | enforced while the body streams in, not once it has landed on disk, so a
    | server that lies about (or omits) its content length cannot exceed it.
    | Default: 5 GB.
    |
    */

    'max_file_size' => (int) env('VERIFICATION_MAX_FILE_SIZE', 5 * 1024 * 1024 * 1024),

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
    | User Agent
    |--------------------------------------------------------------------------
    |
    | Sent on every request to a mod download link (safety check, change
    | detection, and the download itself) so hosts can identify the verifier and
    | allowlist it rather than rate-limit or block it. Includes an info URL.
    |
    */

    'user_agent' => env('VERIFICATION_USER_AGENT', 'ForgeVerifier/1.0 (+https://forge.sp-tarkov.com)'),

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
    | ratio. Kept at three times the maximum file size, because an archive at
    | the limit will normally extract to more than its own size and would
    | otherwise pass the download only to fail extraction. Default: 15 GB.
    |
    */

    'max_extracted_size' => (int) env('VERIFICATION_MAX_EXTRACTED_SIZE', 15 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Tree Entries
    |--------------------------------------------------------------------------
    |
    | The most file paths kept from a verified archive. The container caps its
    | own reported list at this count and the host re-caps it, so neither the
    | JSON returned over stdout nor the stored column can grow without bound for
    | an archive with an enormous number of files. Excess entries are dropped
    | and the result is flagged as truncated.
    |
    */

    'max_file_tree_entries' => (int) env('VERIFICATION_MAX_FILE_TREE_ENTRIES', 10000),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Timeout values (in seconds) for each stage of the verification pipeline. The container stage covers hashing and
    | extracting an archive up to the maximum file size on a single CPU, so its ceiling is sized for the largest
    | legitimate upload rather than the typical one.
    |
    */

    'timeouts' => [
        'dns' => (float) env('VERIFICATION_DNS_TIMEOUT', 5),
        'download' => (int) env('VERIFICATION_DOWNLOAD_TIMEOUT', 900),
        'container' => (int) env('VERIFICATION_CONTAINER_TIMEOUT', 1800),
        'build' => (int) env('VERIFICATION_BUILD_TIMEOUT', 600),
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
        'running_minutes' => (int) env('VERIFICATION_STALE_RUNNING_MINUTES', 90),
    ],

];
