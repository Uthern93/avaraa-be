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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('item_master')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('batch_id')->nullable();
            $table->enum('movement_type', ['IN', 'OUT']);
            $table->integer('quantity')->default(0);
            $table->string('reference_type')->nullable(); // e.g., inbound, delivery_order
            $table->unsignedBigInteger('reference_id')->nullable(); // polymorphic ID
            $table->timestamps();

            $table->foreign('batch_id')->references('batch_id')->on('inbounds')->nullOnDelete();
            $table->index(['item_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
