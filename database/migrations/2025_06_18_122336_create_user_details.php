<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_details', function (Blueprint $table) {
            $table->id();

            // Foreign Key to Users
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthdate')->nullable();
            $table->integer('age')->nullable();
            $table->integer('sex_id')->nullable(); // Male, Female, etc.

            // Contact & Civil Info
            $table->string('phone_number')->nullable();
            $table->string('civil_status')->nullable(); // Single, Married, etc.
            $table->string('nationality')->nullable();
            $table->string('religion')->nullable();

            // Address
            $table->string('address')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipal')->nullable();
            $table->string('province')->nullable();

            // Church Affiliation
            $table->string('church')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->onDelete('cascade');

            $table->timestamps(); // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};
