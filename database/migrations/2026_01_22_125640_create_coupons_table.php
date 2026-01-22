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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'amount']); // percentage or fixed amount
            $table->decimal('value', 10, 2); // discount value (percentage or amount)
            $table->decimal('min_order_value', 10, 2)->default(0); // minimum order value
            $table->integer('usage_limit')->nullable(); // null means unlimited
            $table->boolean('is_unlimited_time')->default(false);
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('status', ['active', 'scheduled', 'expired', 'deactivated', 'exhausted'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
