<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TutorAvatarTest extends TestCase
{
    public function test_missing_local_avatar_uses_fallback_without_rendering_an_image_request(): void
    {
        $user = (object) [
            'full_name' => 'Nguyễn Minh Anh',
            'avatar_url' => '/uploads/avatars/missing-avatar.jpg',
        ];

        $html = Blade::render('<x-tutor-avatar :user="$user" />', compact('user'));

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('avatar-database', $html);
        $this->assertStringContainsString('Anh', $html);
    }

    public function test_external_avatar_keeps_image_error_fallback_and_dimensions(): void
    {
        $user = (object) [
            'full_name' => 'Nguyễn Minh Anh',
            'avatar_url' => 'https://lh3.googleusercontent.com/avatar.jpg',
        ];

        $html = Blade::render('<x-tutor-avatar :user="$user" size="large" />', compact('user'));

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('width="116"', $html);
        $this->assertStringContainsString('height="116"', $html);
        $this->assertStringContainsString('onerror=', $html);
        $this->assertStringContainsString('avatar-database', $html);
    }
}
