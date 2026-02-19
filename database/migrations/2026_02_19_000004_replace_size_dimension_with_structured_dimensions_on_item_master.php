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
            $table->dropColumn('size_dimension');
        });

        Schema::table('item_master', function (Blueprint $table) {
            $table->decimal('dimension_width', 8, 2)->nullable()->after('qty_per_carton');
            $table->decimal('dimension_height', 8, 2)->nullable()->after('dimension_width');
            $table->decimal('dimension_depth', 8, 2)->nullable()->after('dimension_height');
            $table->enum('dimension_unit', ['cm', 'mm', 'inch'])->default('cm')->after('dimension_depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_master', function (Blueprint $table) {
            $table->dropColumn(['dimension_width', 'dimension_height', 'dimension_depth', 'dimension_unit']);
        });

        Schema::table('item_master', function (Blueprint $table) {
            $table->string('size_dimension', 100)->nullable()->after('qty_per_carton');
        });
    }
};
