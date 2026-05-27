<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('status');
            $table->string('electorate');
            $table->string('vote_visibility');
            $table->boolean('allow_abstain')->default(false);
            $table->boolean('allow_multiple_selections')->default(false);
            $table->decimal('quorum_percent', 5, 2)->nullable();
            $table->string('quorum_basis')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'opens_at', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballots');
    }
};
