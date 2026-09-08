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
        Schema::table('occurrence_histories', function (Blueprint $table) {
            $table->dropForeign(['occurrence_id']);
            $table->foreign('occurrence_id')->references('id')->on('occurrences')->restrictOnDelete();
        });

        Schema::table('service_order_histories', function (Blueprint $table) {
            $table->dropForeign(['service_order_id']);
            $table->foreign('service_order_id')->references('id')->on('service_orders')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_order_histories', function (Blueprint $table) {
            $table->dropForeign(['service_order_id']);
            $table->foreign('service_order_id')->references('id')->on('service_orders')->cascadeOnDelete();
        });

        Schema::table('occurrence_histories', function (Blueprint $table) {
            $table->dropForeign(['occurrence_id']);
            $table->foreign('occurrence_id')->references('id')->on('occurrences')->cascadeOnDelete();
        });
    }
};
