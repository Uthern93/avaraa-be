<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE item_master MODIFY COLUMN dimension_unit ENUM('cm','mm','inch','m') DEFAULT 'cm'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE item_master MODIFY COLUMN dimension_unit ENUM('cm','mm','inch') DEFAULT 'cm'");
    }
};
