<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_profile_change_requests', function (Blueprint $table): void {
            $table->id('change_request_id');
            $table->foreignId('tutor_profile_id')
                ->constrained('tutor_profiles', 'tutor_profile_id')
                ->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();
            $table->string('change_type', 40);
            $table->string('change_action', 20);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['tutor_profile_id', 'status'],
                'idx_tutor_profile_change_status'
            );
            $table->index(
                ['tutor_profile_id', 'change_type', 'target_id'],
                'idx_tutor_profile_change_target'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_profile_change_requests');
    }
};
