<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The month as planned on paper: what comes in, what is meant to go out and
        // what is set aside. Actual spending is compared against it through the category.
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('expense');   // income, expense, saving
            $table->bigInteger('amount_cents');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // null = shared
            $table->string('nature')->nullable();          // expenses only: fixed, variable, optional
            $table->string('group_name')->nullable();      // a label to total up ("Pet", "Subscriptions")
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('months')->nullable();            // null = every month; otherwise [6, 11]
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
