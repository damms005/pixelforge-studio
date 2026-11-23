<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    /** The most a generation can cost, reserved when the user submits it. */
    case Hold = 'hold';
    /** The hold given back once the generation is final. */
    case HoldRelease = 'hold_release';
    /** What a succeeded generation actually cost the user. */
    case Charge = 'charge';
}
