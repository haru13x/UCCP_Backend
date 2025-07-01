<?php

// database/migrations/xxxx_xx_xx_create_event_qrcodes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventQrcodesTable extends Migration
{
    public function up()
    {
        Schema::create('qrcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barcode_id');
            $table->string('qr_path'); // store the QR image file path
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('qrcodes');
    }
}
