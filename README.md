# Afterburner Voting

Team-scoped ballots and vote casting for Afterburner Jetstream applications.

## Installation

```bash
composer require laravel-afterburner/voting
php artisan afterburner:voting:install
```

Add the `HasVoting` trait to your `App\Models\Team` model:

```php
use Afterburner\Voting\Concerns\HasVoting;

class Team extends JetstreamTeam
{
    use HasVoting;
}
```

## Permissions

This package uses existing Afterburner template permission slugs:

- `vote_resolutions` — cast votes on open ballots
- `create_resolutions` — create and publish ballots

The install seeder also adds package-specific permissions:

- `manage_ballots`
- `view_ballot_results`
- `manage_proxy_votes`
- `export_ballot_results`

## Voter units

Votes are keyed to a **voter unit** (morph), not a user. `BallotResponse` stores:

- `cast_by_user_id` — who submitted the vote
- `voter_unit_type` + `voter_unit_id` — what entity the vote represents

The default resolver treats each user as their own voter unit (one person, one vote).

## Strata integration

Strata apps assign one vote per property/lot. Implement a custom resolver:

```php
namespace App\Strata\Voting;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;

class PropertyVoterEligibilityResolver implements VoterEligibilityResolver
{
    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection
    {
        return Property::query()
            ->where('team_id', $ballot->team_id)
            ->where(function ($query) use ($user) {
                $query->where('designated_voter_id', $user->id)
                    ->orWhereHas('activeProxies', fn ($q) => $q->where('proxy_holder_user_id', $user->id));
            })
            ->get()
            ->map(fn (Property $property) => new VoterUnit(Property::class, $property->id))
            ->reject(fn (VoterUnit $unit) => $this->alreadyVoted($ballot, $unit));
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return Property::query()->where('team_id', $ballot->team_id)->count();
    }

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        return $this->eligibleVoterUnits($user, $ballot)->contains(
            fn (VoterUnit $unit) => $unit->matches($voterUnitType, $voterUnitId)
        );
    }

    public function voterUnitLabel(string $voterUnitType, int $voterUnitId): string
    {
        $property = Property::query()->find($voterUnitId);

        return $property ? 'Lot '.$property->lot_number : 'Property #'.$voterUnitId;
    }

    protected function alreadyVoted(Ballot $ballot, VoterUnit $unit): bool
    {
        return $ballot->responses()
            ->where('voter_unit_type', $unit->type)
            ->where('voter_unit_id', $unit->id)
            ->exists();
    }
}
```

Register in `.env`:

```
AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER=App\Strata\Voting\PropertyVoterEligibilityResolver
```

### Critical invariant

`ballot_responses` has a unique constraint on `(ballot_id, voter_unit_type, voter_unit_id)`. Once a lot has voted on a ballot, changing the designated voter cannot allow a second vote for that lot.

### Weighted votes (strata entitlement)

Implement `Afterburner\Voting\Contracts\ProvidesWeightedVotes` on your resolver and return unit entitlement per lot:

```php
use Afterburner\Voting\Contracts\ProvidesWeightedVotes;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;

class PropertyVoterEligibilityResolver implements ProvidesWeightedVotes, VoterEligibilityResolver
{
    public function voterUnitWeight(Ballot $ballot, string $voterUnitType, int $voterUnitId): float
    {
        $property = Property::query()->find($voterUnitId);

        return (float) ($property?->vote_weight ?? 1);
    }
}
```

Tally and CSV/PDF exports use weighted counts when the bound resolver implements this contract.

## Phase 3 features

| Feature | Config / usage |
|---------|----------------|
| Vote revocation (withdraw vote, no re-cast) | `AFTERBURNER_VOTING_ALLOW_VOTE_REVOCATION=true` — tombstone in `ballot_vote_revocations` |
| Scheduled open/close | `AFTERBURNER_VOTING_SCHEDULE_TRANSITIONS=true` — queued jobs on publish + `afterburner:voting:process-scheduled` every minute |
| PDF results export | Install `barryvdh/laravel-dompdf`, export via `?format=pdf` on results export route |
| Weighted tally | Resolver implements `ProvidesWeightedVotes` |

Attendance tracking is intentionally deferred to a future meetings package.

## Team voting settings

Team admins can configure defaults at `/teams/{team}/voting-settings`:

| Setting | Purpose |
|---------|---------|
| Default quorum (%) | Applied to new ballots; optional |
| Default vote visibility | Secret, visible after close, or visible in realtime |
| Allow proxy votes | Team-level toggle (global kill switch in config still applies) |
| Lock designation during open ballots | Stored preference for host apps; not enforced by this package |

New ballots inherit team defaults via `TeamVotingSettings`. Individual ballots can override quorum and visibility on the create form.

`HasVoting::votingSettings()` exposes the `TeamVotingSetting` record for the team.

## Custom electorate

For ballots with `electorate = custom`, register a class implementing `CustomElectorateResolver`:

```php
AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER=App\Voting\MyCustomElectorateResolver
```

The class is validated on boot and required when publishing custom-electorate ballots.

## Voter notifications

The package fires `BallotPublished` but does not send email. A stub listener `SendBallotPublishedVoterNotifications` is registered by default. Subscribe to `BallotPublished` in your host app (or replace the listener) to notify eligible voters.

## Routes

- `/teams/{team}/ballots` — ballot index
- `/teams/{team}/ballots/create` — create ballot
- `/teams/{team}/ballots/{ballot}` — ballot detail and voting
- `/teams/{team}/ballots/{ballot}/results` — results after close
- `/teams/{team}/ballots/{ballot}/results/export` — CSV (default) or PDF (`?format=pdf`)
- `/teams/{team}/voting-settings` — team voting defaults (team admins)

## Testing

```bash
composer test
```

Or:

```bash
./vendor/bin/phpunit
```

## Document attachments

When [`laravel-afterburner/documents`](https://github.com/laravel-afterburner/documents) is installed, ballots can link to completed team documents so voters can review supporting material.

1. Run documents migrations (includes `document_links`):

```bash
php artisan migrate
```

2. Ensure both packages are installed in the host app (Strata already uses path repos for both).

On the ballot **show** and **edit** pages, a **Supporting documents** section lists attached files. Preview (eye icon) opens PDFs, images, and plain text in the browser via `teams.documents.preview`; download remains available when permitted.

Linking uses the documents package `document_links` pivot (`LinkDocument` / `UnlinkDocument` actions). Only `upload_status = completed` documents can be attached. Documents must belong to the same team as the ballot.

Disable integration with `AFTERBURNER_VOTING_DOCUMENTS_ENABLED=false`.

## UI conventions

Package views use the host app's Blade button components (same as `afterburner-documents`):

- `<x-button>` — primary actions (Create Ballot, Publish, Submit Vote)
- `<x-secondary-button>` — secondary actions (Save Draft, Close, View Results)
- `<x-danger-button>` — destructive actions
- Icon-only inline row actions — remove/edit/delete beside list rows (SVG + `title`, no visible text; see documents `index.blade.php`)

Do not use raw `bg-indigo-*` classes for buttons. Do not use text labels like "Remove" on compact row actions. Republish views after UI updates:

```bash
php artisan vendor:publish --tag=afterburner-voting-assets --force
```
