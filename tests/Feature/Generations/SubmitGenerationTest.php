<?php

namespace Tests\Feature\Generations;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    private const PROMPT = 'A lighthouse on a cliff at dawn, oil painting';

    public function test_the_estimate_shows_the_price_range_before_submitting(): void
    {
        $this->fakePixelforge();
        $user = User::factory()->withBalance('0.10')->create();

        $this->actingAs($user)
            ->postJson(route('generations.estimate'), [
                'prompt' => str_repeat('a', 400),
                'size' => '1536x1024',
                'quality' => 'high',
                'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
            ])
            ->assertOk()
            ->assertJson([
                'min_price' => '0.357781250',
                'max_price' => '0.467343750',
                'affordable' => false,
            ]);
    }

    public function test_submitting_holds_the_highest_price_and_queues_the_generation(): void
    {
        $this->fakePixelforge();
        Queue::fake();
        $user = User::factory()->withBalance('1.00')->create();

        $this->actingAs($user)
            ->post(route('generations.store'), ['prompt' => self::PROMPT, 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => []])
            ->assertRedirect(route('dashboard'));

        $generation = Generation::sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame($generation->estimated_max_nanodollars, $generation->held_nanodollars);
        $this->assertSame(1_000_000_000 - $generation->held_nanodollars, $user->fresh()->balance_nanodollars);
        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generationId === $generation->id);
    }

    public function test_a_user_who_cannot_afford_the_highest_price_is_blocked(): void
    {
        $this->fakePixelforge();
        Queue::fake();
        $user = User::factory()->withBalance('0.05')->create();

        $this->actingAs($user)
            ->post(route('generations.store'), ['prompt' => self::PROMPT, 'size' => '1024x1024', 'quality' => 'high', 'reference_images' => []])
            ->assertSessionHasErrors('credits');

        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
        $this->assertSame(50_000_000, $user->fresh()->balance_nanodollars);
        Queue::assertNothingPushed();
    }

    public function test_the_job_submits_with_an_idempotency_key_and_records_the_provider_id(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, status: GenerationStatus::Pending);
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response($this->generationObject($generation, 'queued', ['id' => 'gen_01ABC']), 202),
        ]);

        dispatch_sync(new SubmitGeneration($generation->id));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://pixelforge.test/v1/generations'
            && $request->header('Idempotency-Key')[0] === $generation->uuid
            && $request['metadata'] === ['generation_uuid' => $generation->uuid]
            && $request['reference_images'] === []);

        $generation->refresh();
        $this->assertSame('gen_01ABC', $generation->provider_id);
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame(self::RATES, $generation->rates);
    }

    public function test_limits_and_overloads_are_retried_after_retry_after(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, status: GenerationStatus::Pending);
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response(
                ['error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Too many generations.']],
                429,
                ['Retry-After' => '5'],
            ),
        ]);

        $job = (new SubmitGeneration($generation->id))->withFakeQueueInteractions();
        $job->handle(app(PixelforgeClient::class), app(GenerationUpdater::class));

        $job->assertReleased();
        $generation->refresh();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame('concurrency_limit_exceeded', $generation->retry_reason);
        $this->assertSame(1_000_000_000 - $generation->held_nanodollars, $user->fresh()->balance_nanodollars);
    }

    public function test_a_rejected_prompt_fails_for_free(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, status: GenerationStatus::Pending);
        $this->fakePixelforge([
            'pixelforge.test/v1/generations' => Http::response(
                ['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']],
                400,
            ),
        ]);

        dispatch_sync(new SubmitGeneration($generation->id));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertSame(0, $generation->charged_nanodollars);
        $this->assertSame(1_000_000_000, $user->fresh()->balance_nanodollars);
    }

    public function test_giving_up_after_the_last_retry_refunds_the_hold(): void
    {
        $user = User::factory()->withBalance('1.00')->create();
        $generation = $this->heldGeneration($user, status: GenerationStatus::Pending);

        (new SubmitGeneration($generation->id))->failed(null);

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertSame(1_000_000_000, $user->fresh()->balance_nanodollars);
        $this->assertSame(
            [CreditTransactionType::Hold, CreditTransactionType::HoldRelease],
            $generation->creditTransactions()->orderBy('id')->get()->pluck('type')->all(),
        );
    }
}
