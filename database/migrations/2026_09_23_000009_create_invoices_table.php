<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique(
                    [
                        'merchant_id',
                        'subscription_id',
                        'billing_period_start',
                        'billing_period_end',
                    ],
                    'invoices_subscription_billing_period_unique'
                );
                $table->index(
                    ['merchant_id', 'customer_id', 'status'],
                    'invoices_merchant_customer_status_index'
                );
                $table->index(
                    ['merchant_id', 'billing_period_start', 'billing_period_end'],
                    'invoices_merchant_billing_period_index'
                );
            });

            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->dateTime('billing_period_start');
            $table->dateTime('billing_period_end');
            $table->string('status', 30);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->char('currency', 3);
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();

            $table->unique([
                'merchant_id',
                'subscription_id',
                'billing_period_start',
                'billing_period_end',
            ], 'invoices_subscription_billing_period_unique');
            $table->index(
                ['merchant_id', 'customer_id', 'status'],
                'invoices_merchant_customer_status_index'
            );
            $table->index(
                ['merchant_id', 'billing_period_start', 'billing_period_end'],
                'invoices_merchant_billing_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
