<?php

namespace Tests\Feature\Generations;

use App\Console\Commands\SyncActiveGenerations;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    /** Docs example usage costs 0.305510000; plus 25% = 0.381887500. */
    private const CHARGE = 381_887_500;

    private const ONE_DOLLAR = 1_000_000_000;

    public function test_a_succeeded_generation_is_charged_its_exact_cost_plus_markup(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, ['held_nanodollars' => 500_000_000], GenerationStatus::Processing);

        $this->postWebhook($this->generationObject($generation, 'succeeded'), 'evt_1')->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(305_510_000, $generation->provider_cost_nanodollars);
        $this->assertSame(self::CHARGE, $generation->charged_nanodollars);
        $this->assertNotNull($generation->image_url);
        $this->assertSame(self::ONE_DOLLAR - self::CHARGE, $user->fresh()->balance_nanodollars);
    }

    public function test_an_event_delivered_twice_is_recorded_once(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, ['held_nanodollars' => 500_000_000], GenerationStatus::Processing);
        $object = $this->generationObject($generation, 'succeeded');

        $this->postWebhook($object, 'evt_1')->assertNoContent();
        $this->postWebhook($object, 'evt_1')->assertNoContent();
        // A different event (or a poll) with the same final state must not charge again either.
        $this->postWebhook($object, 'evt_2')->assertNoContent();

        $this->assertDatabaseCount('webhook_events', 2);
        $this->assertSame(1, $generation->creditTransactions()->where('type', CreditTransactionType::Charge)->count());
        $this->assertSame(self::ONE_DOLLAR - self::CHARGE, $user->fresh()->balance_nanodollars);
    }

    public function test_a_late_processing_event_never_moves_a_finished_generation_back(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, ['held_nanodollars' => 500_000_000]);

        $this->postWebhook($this->generationObject($generation, 'succeeded'), 'evt_succeeded');
        $this->postWebhook($this->generationObject($generation, 'processing'), 'evt_processing')->assertNoContent();

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
    }

    public function test_a_webhook_that_arrives_before_the_create_request_returns_is_matched_by_metadata(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, status: GenerationStatus::Pending);
        $this->assertNull($generation->provider_id);

        $this->postWebhook($this->generationObject($generation, 'processing', ['id' => 'gen_EARLY']), 'evt_1')->assertNoContent();

        $generation->refresh();
        $this->assertSame('gen_EARLY', $generation->provider_id);
        $this->assertSame(GenerationStatus::Processing, $generation->status);
    }

    public function test_a_failed_generation_costs_nothing(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user);

        $this->postWebhook($this->generationObject($generation, 'failed'), 'evt_1')->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->charged_nanodollars);
        $this->assertSame('The model could not produce an image.', $generation->error_message);
        $this->assertSame(self::ONE_DOLLAR, $user->fresh()->balance_nanodollars);
    }

    public function test_requests_with_a_bad_or_old_signature_are_rejected(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user);
        $object = $this->generationObject($generation, 'succeeded');

        $this->postWebhook($object, 'evt_1', secret: 'whsec_wrong')->assertStatus(400);
        $this->postWebhook($object, 'evt_1', timestamp: now()->subMinutes(10)->getTimestamp())->assertStatus(400);

        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertSame(GenerationStatus::Queued, $generation->fresh()->status);
    }

    public function test_quiet_generations_are_polled_and_settled_through_the_same_rules(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, ['held_nanodollars' => 500_000_000], GenerationStatus::Processing);
        $this->travel(5)->minutes();
        $this->fakePixelforge([
            "pixelforge.test/v1/generations/{$generation->provider_id}" => Http::response($this->generationObject($generation, 'succeeded')),
        ]);

        $this->artisan(SyncActiveGenerations::class)->assertSuccessful();
        $this->artisan(SyncActiveGenerations::class, ['--stale-after' => 0])->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
        $this->assertSame(self::ONE_DOLLAR - self::CHARGE, $user->fresh()->balance_nanodollars);
    }
}
