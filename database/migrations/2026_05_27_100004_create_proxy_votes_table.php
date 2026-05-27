<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->string('grantor_voter_unit_type');
            $table->unsignedBigInteger('grantor_voter_unit_id');
            $table->foreignId('proxy_holder_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['ballot_id', 'grantor_voter_unit_type', 'grantor_voter_unit_id'],
                'proxy_votes_ballot_grantor_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_votes');
    }
};
