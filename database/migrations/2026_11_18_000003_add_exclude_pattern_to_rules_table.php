<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            // "Except when the description also contains…": a cash machine at a fuel
            // station is not a fuel purchase.
            $table->string('exclude_pattern')->nullable()->after('pattern');
        });
    }

    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('exclude_pattern');
        });
    }
};
