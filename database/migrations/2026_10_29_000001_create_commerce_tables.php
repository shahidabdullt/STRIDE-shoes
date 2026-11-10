<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_id', 36);
            $table->enum('status', ['open', 'converted', 'abandoned'])->default('open');
            $table->timestamps();

            $table->index(['visitor_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quantity');
            $table->enum('added_via', ['ui', 'chat', 'voice'])->default('ui');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            // Attribution to the assistant conversation. The conversations table arrives in a later phase,
            // which will add the foreign key.
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('customer_name');
            $table->string('email');
            $table->json('address');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('shipping_cents');
            $table->unsignedInteger('total_cents');
            $table->char('currency', 3);
            $table->enum('status', ['placed', 'cancelled'])->default('placed');
            $table->enum('payment_status', ['simulated'])->default('simulated');
            $table->timestamp('placed_at');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('size', 3, 1);
            $table->string('sku');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
