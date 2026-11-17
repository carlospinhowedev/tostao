<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->bigInteger('amount_cents');
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('frequency')->default('monthly'); // weekly, monthly, yearly
            $table->date('next_date');
            $table->unsignedTinyInteger('day_of_month')->nullable(); // anchor, so the 31st does not drift to the 28th
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
