<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('code');
            $table->integer('point_cost')->default(0)->after('description');
            $table->string('icon')->nullable()->after('point_cost');
            $table->boolean('is_active')->default(true)->after('icon');
            $table->integer('stock')->default(-1)->after('is_active'); // -1 means unlimited
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['description', 'point_cost', 'icon', 'is_active', 'stock']);
        });
    }
};
