<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Neutral plays (end of period, some timeouts) have no team attribution in the
// source feeds; the FK to wnba_teams forbids '' so the column must allow null.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wnba_plays', function (Blueprint $table) {
            $table->string('team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wnba_plays', function (Blueprint $table) {
            $table->string('team_id')->nullable(false)->change();
        });
    }
};
