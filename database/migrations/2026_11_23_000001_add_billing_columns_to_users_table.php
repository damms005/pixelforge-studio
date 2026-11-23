<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Credits are US dollars, stored as integer nanodollars (1 USD = 1,000,000,000).
            $table->bigInteger('balance_nanodollars')->default(0)->after('password');
            $table->boolean('is_admin')->default(false)->after('balance_nanodollars');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance_nanodollars', 'is_admin']);
        });
    }
};
