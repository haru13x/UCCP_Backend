<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->foreignId('church_location_id')->nullable()->after('church')->constrained('church_location')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropForeign(['church_location_id']);
            $table->dropColumn('church_location_id');
        });
    }
};