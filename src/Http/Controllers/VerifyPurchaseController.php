<?php

namespace Bijay\Iap\Http\Controllers;

use Bijay\Iap\Facades\Iap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VerifyPurchaseController
{
    /**
     * Verify an in-app purchase or subscription.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|in:ios,apple,android,google',
            'user_id' => 'required|exists:users,id',
            'payload' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $platform = $request->input('platform');
        $userId = $request->input('user_id');
        $payload = $request->input('payload');

        try {
            $result = Iap::verify($platform, $payload);

            if (!$result->valid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase verification failed',
                    'data' => $result->toArray(),
                ], 400);
            }

            // Store or update subscription in database
            $subscription = $this->storeSubscription($userId, $result);

            return response()->json([
                'success' => true,
                'message' => 'Purchase verified successfully',
                'data' => [
                    'verification' => $result->toArray(),
                    'subscription' => $subscription,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Purchase verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store or update subscription in database.
     */
    protected function storeSubscription(int $userId, $result): array
    {
        $table = config('iap.table', 'iap_subscriptions');

        $subscription = DB::table($table)->updateOrInsert(
            [
                'user_id' => $userId,
                'transaction_id' => $result->originalTransactionId,
            ],
            [
                'platform' => $result->platform,
                'product_id' => $result->productId,
                'status' => $result->status,
                'expires_at' => $result->expiresAt,
                'raw_data' => json_encode($result->rawData),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        $subscription = DB::table($table)
            ->where('user_id', $userId)
            ->where('transaction_id', $result->originalTransactionId)
            ->first();

        return (array) $subscription;
    }
}

