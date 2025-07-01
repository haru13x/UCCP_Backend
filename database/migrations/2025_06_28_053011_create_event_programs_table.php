<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventProgramsTable extends Migration
{
    public function up()
    {
        Schema::create('event_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('activity');
            $table->string('speaker')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_programs');
    }
}
