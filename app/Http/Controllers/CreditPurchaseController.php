<?php

namespace App\Http\Controllers;

use App\Billing\Ledger;
use App\Billing\Nanodollars;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * A fake purchase: no payment is taken, credits are added straight away.
 */
class CreditPurchaseController extends Controller
{
    public const PACKS_IN_DOLLARS = [1, 5, 10];

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'dollars' => ['required', 'integer', Rule::in(self::PACKS_IN_DOLLARS)],
        ]);

        $ledger->purchase($request->user(), $validated['dollars'] * Nanodollars::PER_DOLLAR);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added \${$validated['dollars']} in credits."]);

        return back();
    }
}
