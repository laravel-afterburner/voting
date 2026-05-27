<?php

use Afterburner\Voting\Http\Controllers\BallotsController;
use Afterburner\Voting\Http\Controllers\ExportBallotResultsController;
use Afterburner\Voting\Http\Controllers\VotingSettingsController;
use Afterburner\Voting\Models\Ballot;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/teams/{team}/ballots', [BallotsController::class, 'index'])
        ->name('teams.ballots.index');

    Route::get('/teams/{team}/ballots/create', [BallotsController::class, 'create'])
        ->name('teams.ballots.create')
        ->middleware('can:create,'.Ballot::class.',team');

    Route::get('/teams/{team}/ballots/{ballot}', [BallotsController::class, 'show'])
        ->name('teams.ballots.show');

    Route::get('/teams/{team}/ballots/{ballot}/results', [BallotsController::class, 'results'])
        ->name('teams.ballots.results');

    Route::get('/teams/{team}/ballots/{ballot}/results/export', ExportBallotResultsController::class)
        ->name('teams.ballots.results.export')
        ->middleware('can:exportResults,ballot');

    Route::get('/teams/{team}/ballots/{ballot}/edit', [BallotsController::class, 'edit'])
        ->name('teams.ballots.edit')
        ->middleware('can:update,ballot');

    Route::get('/teams/{team}/voting-settings', VotingSettingsController::class)
        ->name('teams.voting-settings')
        ->middleware('can:update,team');
});
