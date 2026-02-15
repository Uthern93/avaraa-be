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
        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('item_master')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('batch_id')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('maintenance_date')->nullable();
            $table->smallInteger('manufacturing_year')->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->foreign('batch_id')->references('batch_id')->on('inbounds')->nullOnDelete();
            $table->index(['item_id', 'warehouse_id', 'bin_id', 'batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_stocks');
    }
};
