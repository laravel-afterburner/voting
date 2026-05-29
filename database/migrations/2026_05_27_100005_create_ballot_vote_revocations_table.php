<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_vote_revocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->string('voter_unit_type');
            $table->unsignedBigInteger('voter_unit_id');
            $table->foreignId('revoked_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ballot_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('revoked_at');
            $table->timestamps();

            $table->unique(
                ['ballot_id', 'voter_unit_type', 'voter_unit_id'],
                'ballot_vote_revocations_ballot_voter_unit_unique'
            );
        });

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

    public function down(): void
    {
        Schema::dropIfExists('team_voting_settings');
        Schema::dropIfExists('ballot_vote_revocations');
    }
};
