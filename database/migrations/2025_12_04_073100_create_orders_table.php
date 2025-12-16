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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->morphs('orderable'); // This creates orderable_id and orderable_type for polymorphic relationship
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Order details
            $table->string('order_number')->unique();
            $table->unsignedTinyInteger('progress')->default(0); // percentage 0–100
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('payment_status')->default('pending')->nullable(); //pending, paid, failed, refunded

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};


