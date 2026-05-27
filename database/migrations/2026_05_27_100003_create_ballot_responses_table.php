<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cast_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('voter_unit_type')->nullable();
            $table->unsignedBigInteger('voter_unit_id')->nullable();
            $table->unsignedBigInteger('proxy_vote_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('cast_at');
            $table->timestamps();

            $table->unique(['ballot_id', 'voter_unit_type', 'voter_unit_id'], 'ballot_responses_ballot_voter_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_responses');
    }
};
