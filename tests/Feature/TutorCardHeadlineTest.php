<?php

namespace Tests\Feature;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TutorCardHeadlineTest extends TestCase
{
    public function test_directory_card_displays_database_headline(): void
    {
        $html = $this->renderDirectoryCard('Gia sư Toán THCS – Dễ hiểu, kiên nhẫn');

        $this->assertStringContainsString('Nguyễn Minh Anh', $html);
        $this->assertStringContainsString('Gia sư Toán THCS – Dễ hiểu, kiên nhẫn', $html);
    }

    public function test_directory_card_remains_safe_for_legacy_null_headline(): void
    {
        $html = $this->renderDirectoryCard(null);

        $this->assertStringContainsString('Nguyễn Minh Anh', $html);
        $this->assertStringNotContainsString('tutor-subject', $html);
    }

    private function renderDirectoryCard(?string $headline): string
    {
        $user = new User([
            'full_name' => 'Nguyễn Minh Anh',
        ]);
        $tutor = new TutorProfile([
            'headline' => $headline,
            'hourly_rate' => 200000,
            'supports_online' => true,
            'supports_offline' => false,
        ]);
        $tutor->tutor_profile_id = 42;
        $tutor->setRelation('user', $user);
        $tutor->setRelation('tutorSubjects', collect());
        $tutor->setRelation('teachingAreas', collect());

        return Blade::render(
            '<x-tutor-card :tutor="$tutor" variant="directory" />',
            ['tutor' => $tutor]
        );
    }
}
