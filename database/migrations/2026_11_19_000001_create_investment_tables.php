<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What an investment account was worth on a day. The transactions of such an
        // account are only the money moved in and out; this is the rest of the story.
        Schema::create('account_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->bigInteger('value_cents');
            $table->string('source')->default('manual'); // manual or a broker key
            $table->timestamps();

            $table->unique(['account_id', 'date']);
        });

        // Which Tostão account a broker (Trading 212...) feeds. Credentials live in .env.
        Schema::create('broker_links', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('sync_from')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_links');
        Schema::dropIfExists('account_valuations');
    }
};
