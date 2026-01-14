<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('external_id');
            $table->string('entity'); // product, variant, counterparty, etc.
            $table->string('action'); // CREATE, UPDATE, DELETE
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['account_id', 'external_id']);
            $table->index('account_id');
            $table->index(['account_id', 'entity', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
