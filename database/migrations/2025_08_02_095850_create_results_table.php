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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('game_type')->default('spin_wheel'); // Type of game
            $table->string('game_code')->nullable(); // Game session code
            $table->json('spin_data'); // Store complete spin result data
            $table->string('result_english'); // Prize name in English
            $table->string('result_khmer'); // Prize name in Khmer
            $table->string('result_color'); // Prize color
            $table->integer('segment_index'); // Which segment (0-14)
            $table->decimal('spin_angle', 8, 2); // Final spin angle
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->string('ip_address')->nullable(); // Track IP for fraud prevention
            $table->string('user_agent')->nullable(); // Track user agent
            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'game_type']);
            $table->index(['created_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
