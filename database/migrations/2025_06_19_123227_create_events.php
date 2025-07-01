<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('time')->nullable();
            $table->string('category')->nullable();
            $table->string('organizer')->nullable();
            $table->string('contact')->nullable();
            $table->integer('attendees')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();

            // Common fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('status_id')->default(1);
            $table->timestamps();

            // Optional foreign keys (if you have users and statuses tables)
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('status_id')->references('id')->on('statuses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
