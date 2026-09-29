<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_keeps_the_existing_google_oauth_entry_point(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Đăng nhập')
            ->assertSee('Tiếp tục với Google')
            ->assertSee('href="'.route('auth.google.redirect').'"', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('class="login-page"', false);
    }

    public function test_google_entry_route_still_starts_the_existing_oauth_flow(): void
    {
        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://accounts.google.com/o/oauth2/v2/auth?',
            (string) $response->headers->get('Location'),
        );
        $this->assertNotEmpty(session('google_oauth_state'));
    }
}
