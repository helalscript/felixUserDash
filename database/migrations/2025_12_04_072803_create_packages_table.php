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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable(); // Best for
            $table->decimal('price', 10, 2);
            $table->string('explore_link')->nullable();
            $table->longText('specification_text')->nullable(); // Optional long description
            $table->json('features')->nullable(); // Includes list
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('icon_images')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
