<?php

namespace App\Http\Controllers;

use App\Generations\WebhookEventHandler;
use App\Pixelforge\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSignature $signature, WebhookEventHandler $handler): Response
    {
        // Verify against the raw body, before any JSON decoding.
        $payload = $request->getContent();

        if (! $signature->isValid($payload, $request->header('Pixelforge-Signature'), now()->getTimestamp())) {
            return response('Invalid Pixelforge signature.', 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['id'], $event['type'])) {
            return response('Malformed event.', 400);
        }

        $handler->handle($event);

        return response()->noContent();
    }
}
