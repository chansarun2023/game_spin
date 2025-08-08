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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Add unique identifier for better token management
            $table->string('unique_identifier', 64)->unique()->after('token');

            // Add device info for better tracking
            $table->string('device_type')->nullable()->after('unique_identifier');
            $table->string('device_info')->nullable()->after('device_type');

            // Add session info
            $table->string('session_id', 64)->nullable()->after('device_info');

            // Add index for better performance
            $table->index(['tokenable_type', 'tokenable_id', 'unique_identifier'], 'pat_tokenable_unique_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('pat_tokenable_unique_idx');
            $table->dropColumn(['unique_identifier', 'device_type', 'device_info', 'session_id']);
        });
    }
};
