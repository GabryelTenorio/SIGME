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
            $table->foreignId('emergency_authorized_by')->nullable()->after('completed_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('emergency_authorized_at')->nullable()->after('emergency_authorized_by');
            $table->text('emergency_reason')->nullable()->after('emergency_authorized_at');
            $table->timestamp('emergency_ratification_due_at')->nullable()->after('emergency_reason')->index();
            $table->foreignId('emergency_ratified_by')->nullable()->after('emergency_ratification_due_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('emergency_ratified_at')->nullable()->after('emergency_ratified_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['emergency_authorized_by']);
            $table->dropForeign(['emergency_ratified_by']);
            $table->dropIndex(['emergency_ratification_due_at']);
            $table->dropColumn([
                'emergency_authorized_by',
                'emergency_authorized_at',
                'emergency_reason',
                'emergency_ratification_due_at',
                'emergency_ratified_by',
                'emergency_ratified_at',
            ]);
        });
    }
};
