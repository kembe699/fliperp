<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Everything the app writes (company logos, employee photos, signed
        // contract PDFs, receipts) goes through Storage::disk('public') —
        // this single disk switches between local dev storage and an
        // S3-compatible bucket (e.g. Cloudflare R2) via PUBLIC_DISK_DRIVER,
        // so no application code needs to know which one is active.
        'public' => [
            'driver' => env('PUBLIC_DISK_DRIVER', 'local'),
            // "root" is a local-filesystem base path — it must only apply to
            // the "local" driver. The S3 adapter also reads "root" and uses
            // it as a literal object-key prefix, so setting it unconditionally
            // was silently prefixing every uploaded file's S3 key with
            // "app/storage/app/public/...", producing photo_url values that
            // 404 against the bucket.
            'root' => env('PUBLIC_DISK_DRIVER', 'local') === 'local' ? storage_path('app/public') : null,
            'url' => env('PUBLIC_DISK_URL', env('APP_URL').'/storage'),
            'visibility' => 'public',
            // Must be true: with throw=false, a failed S3 write (bad
            // credentials, wrong bucket/endpoint, etc.) returns false from
            // Storage rather than raising, but callers still treat the
            // upload as successful and persist a photo_url for a file that
            // was never actually written — a silent failure that looks like
            // a working upload until someone tries to load the image.
            'throw' => true,
            'report' => false,

            // Only read when PUBLIC_DISK_DRIVER=s3; ignored by the "local" driver.
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
