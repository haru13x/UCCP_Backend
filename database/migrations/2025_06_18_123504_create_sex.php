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
        Schema::create('sexes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();             // e.g., Male, Female, Other
            $table->string('code')->unique();             // e.g., M, F, O
            $table->string('description')->nullable();    // Optional
            $table->foreignId('status_id')->constrained('statuses')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();                         // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sexes');
    }
};
