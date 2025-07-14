<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('statusId')->nullable();
            $table->timestamp('registered_time')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->time('time_in')->nullable();
            $table->unsignedBigInteger('registeredtypeId')->nullable();
            $table->unsignedBigInteger('attendTypeId')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('attend')->nullable();
            $table->boolean('is_attend')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_registrations');
    }
};
