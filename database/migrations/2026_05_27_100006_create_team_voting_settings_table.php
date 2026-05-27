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
        Schema::create('team_voting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->decimal('default_quorum_percent', 5, 2)->nullable();
            $table->string('default_vote_visibility')->default('visible_after_close');
            $table->boolean('allow_proxy_votes')->default(true);
            $table->boolean('lock_designation_during_open_ballots')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_voting_settings');
    }
};
