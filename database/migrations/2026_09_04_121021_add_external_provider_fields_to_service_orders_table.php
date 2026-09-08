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
        Schema::table('service_orders', function (Blueprint $table) {
            $table->string('external_provider_name')->nullable()->after('external_service');
            $table->text('external_service_description')->nullable()->after('external_provider_name');
            $table->string('external_provider_contact')->nullable()->after('external_service_description');
            $table->string('external_provider_tax_id', 32)->nullable()->after('external_provider_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn([
                'external_provider_name',
                'external_service_description',
                'external_provider_contact',
                'external_provider_tax_id',
            ]);
        });
    }
};
