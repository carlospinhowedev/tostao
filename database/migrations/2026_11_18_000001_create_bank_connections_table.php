<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('institution_id');
            $table->string('institution_name');
            $table->string('state')->unique();          // ties the bank's redirect back to this row
            $table->text('session_id')->nullable();     // encrypted
            $table->string('status')->default('pending'); // pending, active, expired, error
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->string('remote_id');
            $table->string('name');
            $table->string('iban')->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sync_from')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_connection_id', 'remote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('bank_connections');
    }
};
