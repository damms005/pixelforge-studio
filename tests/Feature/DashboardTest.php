<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_the_dashboard_shows_the_generate_form_balance_and_history()
    {
        $this->fakePixelforge();
        $user = User::factory()->withBalance('2.5')->create();
        $this->heldGeneration($user);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('generations/Index')
                ->where('options.sizes', ['1024x1024', '1536x1024', '1024x1536'])
                ->where('balance', '2.414000000')
                ->has('generations', 1)
                ->where('generations.0.status', 'queued')
                ->where('generations.0.charged', null)
            );
    }
}
