<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('iap.table', 'iap_subscriptions');
        
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['ios', 'android'])->index();
            $table->string('product_id')->index();
            $table->string('transaction_id')->unique()->index();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('iap.table', 'iap_subscriptions');
        Schema::dropIfExists($tableName);
    }
};

