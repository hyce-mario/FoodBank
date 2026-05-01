<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->string('supplier_name', 200);
            $table->date('order_date');
            $table->date('received_date')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();

            // Set when status transitions to 'received' — links the PO to its
            // generated FinanceTransaction so reports can answer "what did we
            // spend on inventory?" without duplicating data.
            $table->foreignId('finance_transaction_id')
                ->nullable()
                ->constrained('finance_transactions')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
