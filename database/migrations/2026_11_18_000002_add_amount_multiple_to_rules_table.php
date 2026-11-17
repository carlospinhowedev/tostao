<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            // Optional second condition: only amounts that are a whole multiple of this.
            // A fuel-station charge of exactly 20,00 is rarely fuel; 15,26 usually is.
            $table->unsignedInteger('amount_multiple_cents')->nullable()->after('pattern');
        });
    }

    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('amount_multiple_cents');
        });
    }
};
