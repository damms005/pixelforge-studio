<?php

namespace App\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every change to a balance is a row in credit_transactions, and the cached
 * users.balance_nanodollars moves by the same amount in the same database transaction.
 */
class Ledger
{
    public function purchase(User $user, int $nanodollars): void
    {
        DB::transaction(fn () => $this->record($user->id, null, CreditTransactionType::Purchase, $nanodollars));
    }

    /**
     * Reserves the most the generation can cost. Returns false, and changes nothing, when the
     * user cannot afford it. The balance check and the debit are one conditional UPDATE, so two
     * submits at the same moment cannot both spend the same credits.
     */
    public function hold(Generation $generation): bool
    {
        $amount = $generation->held_nanodollars;

        $debited = User::whereKey($generation->user_id)
            ->where('balance_nanodollars', '>=', $amount)
            ->decrement('balance_nanodollars', $amount);

        if ($debited === 0) {
            return false;
        }

        CreditTransaction::create([
            'user_id' => $generation->user_id,
            'generation_id' => $generation->id,
            'type' => CreditTransactionType::Hold,
            'amount_nanodollars' => -$amount,
        ]);

        return true;
    }

    /**
     * Gives back the hold and charges what the generation actually cost (nothing if it failed).
     * Call inside the transaction that made the generation final. The unique index on
     * (generation_id, type) makes a second settlement fail instead of charging twice.
     */
    public function settle(Generation $generation): void
    {
        $this->record($generation->user_id, $generation->id, CreditTransactionType::HoldRelease, $generation->held_nanodollars);

        if ($generation->charged_nanodollars > 0) {
            $this->record($generation->user_id, $generation->id, CreditTransactionType::Charge, -$generation->charged_nanodollars);
        }
    }

    private function record(int $userId, ?int $generationId, CreditTransactionType $type, int $amount): void
    {
        CreditTransaction::create([
            'user_id' => $userId,
            'generation_id' => $generationId,
            'type' => $type,
            'amount_nanodollars' => $amount,
        ]);

        User::whereKey($userId)->increment('balance_nanodollars', $amount);
    }
}
