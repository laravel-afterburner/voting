<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_voting_settings', function (Blueprint $table) {
            $table->decimal('default_vote_weight_per_lot', 8, 4)->nullable()->after('default_quorum_percent');
        });
    }

    public function down(): void
    {
        Schema::table('team_voting_settings', function (Blueprint $table) {
            $table->dropColumn('default_vote_weight_per_lot');
        });
    }
};
