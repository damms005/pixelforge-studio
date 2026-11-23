<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            // Sent to Pixelforge as the Idempotency-Key and in metadata, so a webhook can be
            // matched even when it arrives before the create request returns.
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');

            // Amounts the user pays (provider cost + markup), in nanodollars.
            $table->bigInteger('estimated_min_nanodollars');
            $table->bigInteger('estimated_max_nanodollars');
            $table->bigInteger('held_nanodollars');
            $table->bigInteger('provider_cost_nanodollars')->nullable();
            $table->bigInteger('charged_nanodollars')->nullable();

            $table->json('rates')->nullable();
            $table->json('usage')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('retry_reason')->nullable();
            $table->unsignedSmallInteger('submit_attempts')->default(0);

            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('started_at')->nullable();
            // Pixelforge bills a generation on the UTC day it completed.
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
