<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_usage')) {
            Schema::table('daily_usage', function (Blueprint $table) {
                $table->unique(
                    ['merchant_id', 'customer_id', 'subscription_id', 'usage_date'],
                    'daily_usage_merchant_customer_subscription_date_unique'
                );
                $table->index(
                    ['merchant_id', 'subscription_id', 'usage_date'],
                    'daily_usage_merchant_subscription_date_index'
                );
                $table->index(
                    ['merchant_id', 'customer_id', 'usage_date'],
                    'daily_usage_merchant_customer_date_index'
                );
            });

            return;
        }

        Schema::create('daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('quantity');
            $table->timestamps();

            $table->unique(
                ['merchant_id', 'customer_id', 'subscription_id', 'usage_date'],
                'daily_usage_merchant_customer_subscription_date_unique'
            );
            $table->index(
                ['merchant_id', 'subscription_id', 'usage_date'],
                'daily_usage_merchant_subscription_date_index'
            );
            $table->index(
                ['merchant_id', 'customer_id', 'usage_date'],
                'daily_usage_merchant_customer_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_usage');
    }
};
