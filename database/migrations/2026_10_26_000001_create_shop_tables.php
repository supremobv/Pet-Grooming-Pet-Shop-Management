<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('receptionist');
            $t->boolean('active')->default(true);
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40);
            $t->string('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index('name');
        });
        Schema::create('pets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('species');
            $t->string('breed')->nullable();
            $t->date('birth_date')->nullable();
            $t->decimal('weight', 6, 2)->nullable();
            $t->text('medical_notes')->nullable();
            $t->text('behavior_notes')->nullable();
            $t->date('vaccination_due')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('address')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('duration')->default(60);
            $t->unsignedInteger('price_cents');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('sku')->unique();
            $t->string('category');
            $t->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('price_cents');
            $t->unsignedInteger('cost_cents')->default(0);
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('reorder_level')->default(5);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index('name');
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pet_id')->constrained()->restrictOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->foreignId('groomer_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('starts_at')->index();
            $t->dateTime('ends_at');
            $t->string('status')->default('booked')->index();
            $t->unsignedInteger('price_cents');
            $t->text('notes')->nullable();
            $t->text('grooming_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->uuid('checkout_key')->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('paid');
            $t->unsignedInteger('subtotal_cents');
            $t->unsignedInteger('discount_cents')->default(0);
            $t->unsignedInteger('tax_cents');
            $t->unsignedInteger('total_cents');
            $t->unsignedInteger('tendered_cents');
            $t->string('payment_method');
            $t->string('payment_reference')->nullable();
            $t->json('business_snapshot');
            $t->text('refund_reason')->nullable();
            $t->foreignId('refunded_by')->nullable()->constrained('users');
            $t->timestamp('refunded_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('unit_price_cents');
            $t->unsignedInteger('unit_cost_cents')->default(0);
            $t->unsignedInteger('total_cents');
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('quantity');
            $t->string('reason');
            $t->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->json('data');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'settings', 'stock_movements', 'sale_items', 'sales', 'appointments', 'products', 'services', 'suppliers', 'pets', 'customers'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
