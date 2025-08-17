<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Path to your Firebase service account JSON file
    |
    */
    'credentials' => storage_path(env('FIREBASE_CREDENTIALS', 'app/firebase-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | Your Firebase project ID
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | FCM Tokens Table
    |--------------------------------------------------------------------------
    |
    | The database table used to store FCM tokens
    |
    */
    'tokens_table' => 'fcm_tokens',

    /*
    |--------------------------------------------------------------------------
    | Default Notification Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for notifications
    |
    */
    'defaults' => [
        'android' => [
            'ttl' => '3600s',
            'priority' => 'normal',
            'color' => '#f45342',
            'sound' => 'default',
        ],
        'apns' => [
            'priority' => '10',
            'badge' => 42,
            'sound' => 'default',
        ],
    ],
];