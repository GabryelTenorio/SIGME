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
        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('environment_id')->constrained()->restrictOnDelete();
            $table->foreignId('occurrence_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('occurrences')->nullOnDelete();
            $table->string('protocol', 80)->unique();
            $table->unsignedSmallInteger('protocol_year');
            $table->unsignedBigInteger('protocol_sequence');
            $table->string('title');
            $table->text('description');
            $table->string('impact', 20);
            $table->string('perceived_urgency', 20);
            $table->string('suggested_priority', 20);
            $table->string('confirmed_priority', 20)->nullable();
            $table->string('status', 40)->default('ABERTA');
            $table->text('triage_note')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->string('forwarded_destination')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'protocol_year', 'protocol_sequence'], 'occurrences_school_year_sequence_unique');
            $table->index(['school_id', 'status', 'created_at'], 'occurrences_school_status_created_idx');
            $table->index(['organization_id', 'status'], 'occurrences_org_status_idx');
            $table->index(['reporter_id', 'created_at'], 'occurrences_reporter_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occurrences');
    }
};
