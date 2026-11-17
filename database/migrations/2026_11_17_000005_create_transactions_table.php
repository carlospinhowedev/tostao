<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // null = shared ("Juntos")
            $table->foreignId('paid_by_id')->nullable()->constrained('users')->nullOnDelete(); // who really paid a shared expense
            $table->foreignId('recurring_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->index();
            $table->string('description');
            $table->bigInteger('amount_cents'); // negative = expense, positive = income
            $table->text('notes')->nullable();
            $table->string('import_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['owner_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
