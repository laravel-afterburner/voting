<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Actions\DeleteBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Actions\ReopenBallot;
use Afterburner\Voting\Actions\RevokeVote;
use Afterburner\Voting\Concerns\FlashesNativeBanner;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\DocumentsIntegration;
use Afterburner\Voting\Support\UserBallotResponseSummary;
use Afterburner\Voting\Support\VoterUnitPartitioner;
use App\Models\Team;
use App\Models\User;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use FlashesNativeBanner;
    use InteractsWithBanner;

    public int $teamId;

    public int $ballotId;

    public bool $confirmingBallotDeletion = false;

    public bool $votePerLot = false;

    public int $voteSummaryVersion = 0;

    public bool $showVoteForm = false;

    public function mount(Team $team, Ballot $ballot): void
    {
        if ($ballot->team_id !== $team->id) {
            abort(404);
        }

        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(Auth::user()->can('view', $ballot), 403);

        $this->teamId = $team->id;
        $this->ballotId = $ballot->id;

        $user = Auth::user();
        $this->syncVoteFormModeFromBallot($ballot, $user);
        $this->syncVoteFormVisibility($ballot, $user);
    }

    public function publishBallot(): void
    {
        $ballot = $this->ballot();

        try {
            app(PublishBallot::class)->execute($ballot, Auth::user());
            $this->banner(__('Ballot published successfully.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function revokeVote(int $responseId): void
    {
        $this->revokeVotes([$responseId]);
    }

    /**
     * @param  array<int, int>  $responseIds
     */
    public function revokeVotes(array $responseIds): void
    {
        $ballot = $this->ballot();

        foreach ($responseIds as $responseId) {
            $response = BallotResponse::query()
                ->where('ballot_id', $ballot->id)
                ->where('id', $responseId)
                ->firstOrFail();

            try {
                app(RevokeVote::class)->execute(
                    $ballot,
                    Auth::user(),
                    $response->voter_unit_type,
                    $response->voter_unit_id,
                    request()->ip(),
                    request()->userAgent(),
                );
            } catch (\Throwable $exception) {
                $this->dangerBanner($exception->getMessage());

                return;
            }
        }

        $message = count($responseIds) > 1
            ? __('Your votes have been revoked.')
            : __('Your vote has been revoked.');

        $this->voteSummaryVersion++;
        $this->banner($message);
        $this->dispatch('refresh-notifications');
    }

    public function closeBallot(): void
    {
        $ballot = $this->ballot();

        try {
            app(CloseBallot::class)->execute($ballot, Auth::user());
            $this->banner(__('Ballot closed successfully.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function reopenBallot(): void
    {
        $ballot = $this->ballot();

        try {
            app(ReopenBallot::class)->execute($ballot, Auth::user());
            $this->banner(__('Ballot reopened successfully.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function confirmBallotDeletion(): void
    {
        abort_unless(Auth::user()->can('delete', $this->ballot()), 403);

        $this->confirmingBallotDeletion = true;
    }

    public function cancelBallotDeletion(): void
    {
        $this->confirmingBallotDeletion = false;
    }

    public function deleteBallot()
    {
        $ballot = $this->ballot();

        try {
            app(DeleteBallot::class)->execute($ballot, Auth::user());
            $this->flashSuccessBanner(__('Ballot deleted successfully.'));

            return $this->redirectRoute('teams.ballots.index', ['team' => $this->teamId]);
        } catch (\Throwable $exception) {
            $this->confirmingBallotDeletion = false;
            $this->dangerBanner($exception->getMessage());
        }
    }

    #[On('vote-cast')]
    public function refreshAfterVote(): void
    {
        $this->voteSummaryVersion++;
        $this->syncVoteFormModeFromBallot($this->ballot(), Auth::user());
        $this->showVoteForm = false;
    }

    #[On('ballot-documents-updated')]
    public function refreshBallotDocuments(): void {}

    public function showUpdateVoteForm(): void
    {
        abort_unless(Auth::user()->can('vote', $this->ballot()), 403);

        $this->showVoteForm = true;
    }

    protected function ballot(): Ballot
    {
        return Ballot::query()
            ->with(['options', 'creator', 'team'])
            ->where('team_id', $this->teamId)
            ->findOrFail($this->ballotId);
    }

    protected function syncVoteFormModeFromBallot(Ballot $ballot, User $user): void
    {
        $eligibleUnits = app(VoterEligibilityResolver::class)->eligibleVoterUnits($user, $ballot);
        $ownedLotUnits = app(VoterUnitPartitioner::class)
            ->partition($user, $ballot, $eligibleUnits)['owned_lot_units'];

        $this->syncVoteFormMode($ownedLotUnits, $this->userResponses($ballot, $user));
    }

    /**
     * @param  Collection<int, VoterUnit>  $ownedLotUnits
     * @param  Collection<int, BallotResponse>  $responses
     */
    protected function syncVoteFormMode($ownedLotUnits, $responses): void
    {
        $this->votePerLot = app(VoterUnitPartitioner::class)
            ->shouldUsePerLotVoteForm($ownedLotUnits, $responses);
    }

    protected function syncVoteFormVisibility(Ballot $ballot, User $user): void
    {
        $this->showVoteForm = $user->can('vote', $ballot)
            && $this->userResponses($ballot, $user)->isEmpty();
    }

    /**
     * @return Collection<int, BallotResponse>
     */
    protected function userResponses(Ballot $ballot, User $user)
    {
        return BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where(function ($query) use ($user) {
                $query->where('cast_by_user_id', $user->id)
                    ->orWhere(function ($inner) use ($user) {
                        $inner->where('voter_unit_type', User::class)
                            ->where('voter_unit_id', $user->id);
                    });
            })
            ->with('option')
            ->orderBy('cast_at')
            ->get();
    }

    public function render()
    {
        $ballot = $this->ballot();
        $team = Team::query()->findOrFail($this->teamId);
        $user = Auth::user();
        $resolver = app(VoterEligibilityResolver::class);

        $eligibleUnits = $resolver->eligibleVoterUnits($user, $ballot);
        $ownedLotUnits = app(VoterUnitPartitioner::class)
            ->partition($user, $ballot, $eligibleUnits)['owned_lot_units'];
        $responses = $this->userResponses($ballot, $user);

        $voteSummaries = app(UserBallotResponseSummary::class)->summarize($ballot, $responses);

        $showSupportingDocuments = false;

        if (DocumentsIntegration::isEnabled()) {
            $ballot->loadCount('linkedDocuments');
            $showSupportingDocuments = $ballot->isOpen() || $ballot->linked_documents_count > 0;
        }

        $ballot->loadCount('responses');

        return view('afterburner-voting::ballots.livewire.show', [
            'team' => $team,
            'ballot' => $ballot,
            'responses' => $responses,
            'voteSummaries' => $voteSummaries,
            'showSupportingDocuments' => $showSupportingDocuments,
            'hasRecordedVotes' => $ballot->responses_count > 0,
            'canVote' => $user->can('vote', $ballot),
            'canPublish' => $user->can('publish', $ballot),
            'canClose' => $user->can('close', $ballot),
            'canReopen' => $user->can('reopen', $ballot),
            'canViewResults' => $user->can('viewResults', $ballot),
            'canUpdate' => $user->can('update', $ballot),
            'canDelete' => $user->can('delete', $ballot),
            'allowVoteRevocation' => (bool) config('afterburner-voting.allow_vote_revocation', false),
        ]);
    }
}
