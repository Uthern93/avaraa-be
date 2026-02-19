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
        Schema::table('item_master', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('item_master', function (Blueprint $table) {
            $table->tinyInteger('storage_type')->default(1)->after('weight')->comment('1=Pallet, 2=Carton, 3=Odd Size');
            $table->integer('qty_per_pallet')->nullable()->after('storage_type');
            $table->integer('qty_per_carton')->nullable()->after('qty_per_pallet');
            $table->string('size_dimension', 100)->nullable()->after('qty_per_carton')->comment('W x H x D');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_master', function (Blueprint $table) {
            $table->dropColumn(['storage_type', 'qty_per_pallet', 'qty_per_carton', 'size_dimension']);
        });

        Schema::table('item_master', function (Blueprint $table) {
            $table->tinyInteger('type')->default(1)->after('weight');
        });
    }
};
