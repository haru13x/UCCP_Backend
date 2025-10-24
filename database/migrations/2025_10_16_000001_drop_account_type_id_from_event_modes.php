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
        if (Schema::hasTable('event_modes') && Schema::hasColumn('event_modes', 'account_type_id')) {
            Schema::table('event_modes', function (Blueprint $table) {
                $table->dropColumn('account_type_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('event_modes') && !Schema::hasColumn('event_modes', 'account_type_id')) {
            Schema::table('event_modes', function (Blueprint $table) {
                $table->integer('account_type_id')->after('id');
            });
        }
    }
};