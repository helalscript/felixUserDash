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
        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('addon_id')
                ->nullable()
                ->constrained('addons')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('sub_title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->default(0);

            $table->enum('status', ['pending', 'active', 'cancelled'])->default('pending');
            $table->string('payment_status')->default('pending')->nullable(); //pending, paid, failed, refunded
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_addons');
    }
};
