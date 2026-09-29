<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutorRegistrationSubmissionMigrationTest extends TestCase
{
    public function test_migration_adds_nullable_marker_without_backfilling_or_changing_status_default(): void
    {
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->bigIncrements('tutor_profile_id');
            $table->string('approval_status', 30)->default('PENDING');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
        $profileId = DB::table('tutor_profiles')->insertGetId([
            'approval_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_09_11_000000_add_submitted_at_to_tutor_profiles_table.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('tutor_profiles', 'submitted_at'));
        $this->assertNull(DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->value('submitted_at'));
        $this->assertSame('PENDING', DB::table('tutor_profiles')->where('tutor_profile_id', $profileId)->value('approval_status'));

        DB::table('tutor_profiles')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame('PENDING', DB::table('tutor_profiles')->latest('tutor_profile_id')->value('approval_status'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('tutor_profiles', 'submitted_at'));
    }
}
