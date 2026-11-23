<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class CreditsAndReportTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    public function test_buying_credits_adds_them_to_the_balance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('credits.store'), ['dollars' => 5])->assertRedirect();
        $this->actingAs($user)->post(route('credits.store'), ['dollars' => 1000])->assertSessionHasErrors('dollars');

        $this->assertSame(5_000_000_000, $user->fresh()->balance_nanodollars);
    }

    public function test_the_report_is_for_operators_only(): void
    {
        $this->fakePixelforge();

        $this->actingAs(User::factory()->create())->get(route('admin.report'))->assertForbidden();
    }

    public function test_the_report_shows_spend_cost_and_profit_per_day_and_user_against_pixelforge_usage(): void
    {
        $this->travelTo(now('UTC')->setTime(12, 0));
        $today = now('UTC')->toDateString();
        $user = User::factory()->withBalance('2.00')->create(['name' => 'Ada']);

        foreach (['evt_1', 'evt_2'] as $eventId) {
            $generation = $this->heldGeneration($user, ['held_nanodollars' => 500_000_000], GenerationStatus::Processing);
            $this->postWebhook($this->generationObject($generation, 'succeeded'), $eventId);
        }

        $this->fakePixelforge([
            'pixelforge.test/v1/usage*' => Http::response(['object' => 'usage', 'data' => [
                ['date' => $today, 'generations' => 2, 'cost_usd' => '0.611020000'],
            ]]),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.report'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/SpendReport')
                ->where('days.0.date', $today)
                ->where('days.0.generations', 2)
                ->where('days.0.charged', '0.763775000')
                ->where('days.0.provider_cost', '0.611020000')
                ->where('days.0.profit', '0.152755000')
                ->where('days.0.pixelforge_cost', '0.611020000')
                ->where('days.0.matches_pixelforge', true)
                ->where('days.0.users.0.name', 'Ada')
            );
    }
}
