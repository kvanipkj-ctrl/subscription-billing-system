<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('currency', 3);
            $table->string('billing_interval', 20);
            $table->decimal('base_price', 15, 2);
            $table->unsignedBigInteger('included_units');
            $table->decimal('overage_unit_price', 15, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'name']);
            $table->index(['merchant_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
