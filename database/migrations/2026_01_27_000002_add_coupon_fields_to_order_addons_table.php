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
        Schema::table('order_addons', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('amount')->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->decimal('coupon_value', 10, 2)->nullable()->after('coupon_code');
            $table->decimal('original_amount', 10, 2)->nullable()->after('coupon_value');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_addons', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'coupon_code', 'coupon_value', 'original_amount', 'discount_amount']);
        });
    }
};
