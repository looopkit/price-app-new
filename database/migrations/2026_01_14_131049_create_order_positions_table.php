<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('external_id');
            $table->string('type'); // customerorder, purchaseorder
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('total_quantity', 15, 3); // Общее количество
            $table->decimal('purchase_quantity', 15, 3)->default(0); // Покрытое количество
            $table->decimal('price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->foreignId('purchase_id')->nullable()->constrained('order_positions')->onDelete('set null'); // Связь с заказом поставщику
            $table->timestamps();
            
            $table->unique(['account_id', 'external_id']);
            $table->index('account_id');
            $table->index('product_id');
            $table->index('type');
            $table->index(['account_id', 'type']);
            $table->index('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_positions');
    }
};