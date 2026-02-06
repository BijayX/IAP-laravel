<?php

namespace Bijay\Iap\Http\Controllers;

use Bijay\Iap\Facades\Iap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    /**
     * Handle Apple App Store webhook notifications.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function apple(Request $request): JsonResponse
    {
        try {
            $notification = $request->all();
            $notificationType = $notification['notification_type'] ?? null;

            Log::info('Apple webhook received', [
                'notification_type' => $notificationType,
                'data' => $notification,
            ]);

            // Handle different notification types
            switch ($notificationType) {
                case 'INITIAL_BUY':
                case 'DID_RENEW':
                case 'DID_RECOVER':
                    return $this->handleActiveSubscription($notification, 'ios');
                case 'DID_FAIL_TO_RENEW':
                case 'DID_CANCEL':
                    return $this->handleCancelledSubscription($notification, 'ios');
                case 'EXPIRED':
                    return $this->handleExpiredSubscription($notification, 'ios');
                default:
                    return response()->json([
                        'success' => true,
                        'message' => 'Notification received but not processed',
                    ], 200);
            }
        } catch (\Exception $e) {
            Log::error('Apple webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook',
            ], 500);
        }
    }

    /**
     * Handle Google Play Store webhook notifications.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function google(Request $request): JsonResponse
    {
        try {
            $notification = $request->all();
            $message = $notification['message'] ?? [];
            $subscriptionNotification = $message['subscriptionNotification'] ?? null;

            Log::info('Google webhook received', [
                'data' => $notification,
            ]);

            if (!$subscriptionNotification) {
                return response()->json([
                    'success' => true,
                    'message' => 'No subscription notification found',
                ], 200);
            }

            $notificationType = $subscriptionNotification['notificationType'] ?? null;

            switch ($notificationType) {
                case 1: // SUBSCRIPTION_RECOVERED
                case 2: // SUBSCRIPTION_RENEWED
                case 4: // SUBSCRIPTION_PURCHASED
                    return $this->handleActiveSubscription($notification, 'android');
                case 3: // SUBSCRIPTION_CANCELED
                    return $this->handleCancelledSubscription($notification, 'android');
                case 12: // SUBSCRIPTION_EXPIRED
                    return $this->handleExpiredSubscription($notification, 'android');
                default:
                    return response()->json([
                        'success' => true,
                        'message' => 'Notification received but not processed',
                    ], 200);
            }
        } catch (\Exception $e) {
            Log::error('Google webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook',
            ], 500);
        }
    }

    /**
     * Handle active subscription notification.
     */
    protected function handleActiveSubscription(array $notification, string $platform): JsonResponse
    {
        // Extract purchase information and verify
        // This is a simplified version - you may need to adjust based on actual webhook payload
        $table = config('iap.table', 'iap_subscriptions');

        // Update subscription status to active
        // Note: You'll need to extract the actual transaction/product IDs from the notification
        Log::info("Active subscription notification for {$platform}", ['notification' => $notification]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated',
        ], 200);
    }

    /**
     * Handle cancelled subscription notification.
     */
    protected function handleCancelledSubscription(array $notification, string $platform): JsonResponse
    {
        $table = config('iap.table', 'iap_subscriptions');

        Log::info("Cancelled subscription notification for {$platform}", ['notification' => $notification]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled',
        ], 200);
    }

    /**
     * Handle expired subscription notification.
     */
    protected function handleExpiredSubscription(array $notification, string $platform): JsonResponse
    {
        $table = config('iap.table', 'iap_subscriptions');

        Log::info("Expired subscription notification for {$platform}", ['notification' => $notification]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription expired',
        ], 200);
    }
}

