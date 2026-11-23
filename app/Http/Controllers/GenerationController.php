<?php

namespace App\Http\Controllers;

use App\Billing\CostEstimator;
use App\Billing\Estimate;
use App\Billing\Ledger;
use App\Billing\Nanodollars;
use App\Enums\GenerationStatus;
use App\Http\Requests\GenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Jobs\SubmitGeneration;
use App\Pixelforge\PixelforgeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function __construct(
        private readonly PixelforgeClient $pixelforge,
        private readonly CostEstimator $estimator,
    ) {}

    public function index(Request $request): Response
    {
        $pricing = $this->pixelforge->pricing();
        $user = $request->user();

        return Inertia::render('generations/Index', [
            'options' => [
                'sizes' => $pricing->sizes(),
                'qualities' => $pricing->qualities(),
                'max_prompt_length' => GenerationRequest::MAX_PROMPT_LENGTH,
                'max_reference_images' => GenerationRequest::MAX_REFERENCE_IMAGES,
                'markup_percent' => config('services.pixelforge.markup_percent'),
            ],
            'balance' => Nanodollars::toDecimal($user->balance_nanodollars),
            'generations' => GenerationResource::collection(
                $user->generations()->latest('id')->limit(60)->get(),
            )->resolve(),
        ]);
    }

    public function estimate(GenerationRequest $request): JsonResponse
    {
        $estimate = $this->estimateFor($request);

        return response()->json([
            ...$estimate->toArray(),
            'affordable' => $request->user()->balance_nanodollars >= $estimate->maxPriceNanodollars,
        ]);
    }

    public function store(GenerationRequest $request, Ledger $ledger): RedirectResponse
    {
        $estimate = $this->estimateFor($request);

        $generation = DB::transaction(function () use ($request, $estimate, $ledger) {
            $generation = $request->user()->generations()->create([
                'uuid' => (string) Str::uuid(),
                'status' => GenerationStatus::Pending,
                'prompt' => $request->validated('prompt'),
                'size' => $request->validated('size'),
                'quality' => $request->validated('quality'),
                'reference_images' => $request->referenceImages(),
                'estimated_min_nanodollars' => $estimate->minPriceNanodollars,
                'estimated_max_nanodollars' => $estimate->maxPriceNanodollars,
                'held_nanodollars' => $estimate->maxPriceNanodollars,
            ]);

            if (! $ledger->hold($generation)) {
                throw ValidationException::withMessages([
                    'credits' => 'You need at least $'.Nanodollars::toDecimal($estimate->maxPriceNanodollars)
                        .' in credits for this generation (its highest possible price). Buy credits to continue.',
                ]);
            }

            return $generation;
        });

        SubmitGeneration::dispatch($generation->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Generation submitted.']);

        return to_route('dashboard');
    }

    private function estimateFor(GenerationRequest $request): Estimate
    {
        return $this->estimator->estimate(
            $this->pixelforge->pricing(),
            $request->validated('prompt'),
            $request->validated('size'),
            $request->validated('quality'),
            $request->referenceImages(),
        );
    }
}
