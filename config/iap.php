<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Apple App Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Apple App Store verification settings here.
    | You can get your shared secret from App Store Connect.
    |
    */

    'apple' => [
        'shared_secret' => env('IAP_APPLE_SHARED_SECRET'),
        'verify_receipt_url' => env('IAP_APPLE_VERIFY_URL', 'https://buy.itunes.apple.com/verifyReceipt'),
        'sandbox_url' => env('IAP_APPLE_SANDBOX_URL', 'https://sandbox.itunes.apple.com/verifyReceipt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Play Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Google Play Store verification settings here.
    | You need to create a service account and download the JSON key file.
    | Place the JSON file in storage/app/private/ and reference it here.
    |
    */

    'google' => [
        'service_account_path' => env('IAP_GOOGLE_SERVICE_ACCOUNT_PATH', storage_path('app/private/google-service-account.json')),
        'package_name' => env('IAP_GOOGLE_PACKAGE_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the database table name for storing subscriptions.
    |
    */

    'table' => env('IAP_TABLE_NAME', 'iap_subscriptions'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Specify the user model class to use for relationships.
    |
    */

    'user_model' => env('IAP_USER_MODEL', \App\Models\User::class),
];

