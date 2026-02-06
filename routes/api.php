<?php

use Bijay\Iap\Http\Controllers\VerifyPurchaseController;
use Bijay\Iap\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IAP Package Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the IapServiceProvider. They handle
| in-app purchase verification and webhook notifications.
|
*/

Route::prefix('iap')->group(function () {
    // Purchase verification endpoint
    Route::post('/verify', [VerifyPurchaseController::class, 'verify'])
        ->name('iap.verify');

    // Webhook endpoints for platform notifications
    Route::post('/webhook/ios', [WebhookController::class, 'apple'])
        ->name('iap.webhook.ios');
    
    Route::post('/webhook/apple', [WebhookController::class, 'apple'])
        ->name('iap.webhook.apple');
    
    Route::post('/webhook/android', [WebhookController::class, 'google'])
        ->name('iap.webhook.android');
    
    Route::post('/webhook/google', [WebhookController::class, 'google'])
        ->name('iap.webhook.google');
});

