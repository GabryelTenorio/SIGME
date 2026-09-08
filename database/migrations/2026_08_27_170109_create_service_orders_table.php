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
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('occurrence_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->unsignedSmallInteger('code_year');
            $table->unsignedBigInteger('code_sequence');
            $table->string('title');
            $table->text('description');
            $table->string('priority_snapshot', 20);
            $table->string('status', 40);
            $table->date('due_date')->nullable();
            $table->dateTime('planned_at')->nullable();
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->boolean('requires_purchase')->default(false);
            $table->boolean('external_service')->default(false);
            $table->boolean('asset_replacement')->default(false);
            $table->boolean('asset_disposal')->default(false);
            $table->boolean('extraordinary_purchase')->default(false);
            $table->boolean('approval_required')->default(false);
            $table->text('notes')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('solution')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'code_year', 'code_sequence'], 'service_orders_school_year_seq_unique');
            $table->index(['school_id', 'status', 'due_date'], 'service_orders_school_status_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
