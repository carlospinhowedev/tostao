<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('bank'); // bank, cash, card, savings
            $table->bigInteger('opening_balance_cents')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // null = joint account
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
