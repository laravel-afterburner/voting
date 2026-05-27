# Afterburner Voting Package — Development Specification

Handoff document for building `laravel-afterburner/voting` as an Afterburner add-on. Use the documents package (`afterburner-documents`) as the canonical reference implementation.

## 1. Project context

**Goal:** Build a pull-in Laravel package (`Afterburner\Voting\`) that provides team-scoped ballots, vote casting, tallying, quorum tracking, and proxy support — without hard-coding strata/property concepts.

**Host app:** Afterburner Jetstream template (`laravel-afterburner/jetstream`). Teams = multi-tenant boundary. Users have team-scoped roles/permissions via `HasAfterburnerRoles`.

**Strata use case (consumer app, not this package):** One vote per property/lot. Multiple co-owners may view documents, but only one designated voter (or proxy holder) casts per lot. The voting package must support this via an extension contract, not built-in Property models.

**What already exists in the template:**

- Permission slugs: `vote_resolutions`, `create_resolutions` (in RoleTemplates, not yet enforced)
- Roadmap items: council-only vs all-member ballots, AGM quorum, proxy votes, result export
- No voting code, models, or migrations yet

**Reference package:** `laravel-afterburner/documents` — mirror its structure, service provider patterns, permission seeder, navigation registration, install command, and test setup.

## 2. Design principles

- **Package owns ballot mechanics; app owns voter identity.**
- **Generic apps:** one user = one vote.
- **Strata apps:** one property/lot = one vote; user is the actor.
- Votes are keyed to a **voter unit**, not a user.
- `BallotResponse` stores both `cast_by_user_id` (who clicked) and a morph `voter_unit` (what entity the vote represents).
- Unique constraint: `(ballot_id, voter_unit_type, voter_unit_id)`.
- **Permissions gate capability; resolver gates eligibility.**
  - `vote_resolutions` = "this user type may participate in voting."
  - `VoterEligibilityResolver` = "which voter units can this user represent right now?"
- Ballot configuration controls electorate, not just roles.
  - Examples: all team members, council only, custom resolver.
- **Designated-voter reassignment must not allow double voting.**
  - Once a voter unit has responded on a ballot, no other user may cast for that unit on that ballot — even if designation changes mid-ballot.
- Follow documents package conventions exactly for naming, publish tags, env vars, and integration hooks.

## 3. Package metadata

| Field | Value |
|-------|-------|
| Name | `laravel-afterburner/voting` |
| Namespace | `Afterburner\Voting\` |
| Repo | `github.com/laravel-afterburner/voting` (sibling to afterburner-documents) |
| Type | library |
| PHP | `^8.2` |
| Requires | `laravel/framework ^11.0`, `laravel-afterburner/jetstream ^1.0\|dev-master`, `livewire/livewire ^3.5` |
| Dev | `orchestra/testbench ^9.0`, `phpunit ^11.0`, `laravel/pint ^1.13` |

**Composer auto-discovery:**

- Provider: `Afterburner\Voting\Providers\VotingServiceProvider`
- Command: `Afterburner\Voting\Console\Commands\InstallCommand`

**Publish tags:**

- `afterburner-voting-config`
- `afterburner-voting-migrations`
- `afterburner-voting-assets`

**Env prefix:** `AFTERBURNER_VOTING_*`

## 4. Directory structure

```
afterburner-voting/
├── composer.json
├── README.md
├── config/
│   └── afterburner-voting.php
├── database/
│   └── migrations/
├── routes/
│   └── web.php
├── resources/
│   └── views/
│       ├── ballots/
│       │   ├── index.blade.php
│       │   ├── show.blade.php
│       │   ├── vote.blade.php
│       │   └── results.blade.php
│       └── settings/
│           └── voting-settings.blade.php
├── src/
│   ├── Actions/
│   │   ├── CreateBallot.php
│   │   ├── UpdateBallot.php
│   │   ├── PublishBallot.php
│   │   ├── CloseBallot.php
│   │   ├── CastVote.php
│   │   ├── RevokeVote.php (optional v1 — see phase plan)
│   │   ├── CreateProxy.php
│   │   └── RevokeProxy.php
│   ├── Concerns/
│   │   └── HasVoting.php
│   ├── Console/
│   │   └── Commands/
│   │       └── InstallCommand.php
│   ├── Contracts/
│   │   └── VoterEligibilityResolver.php
│   ├── Database/
│   │   └── Seeders/
│   │       └── VotingPermissionsSeeder.php
│   ├── Enums/
│   │   ├── BallotStatus.php
│   │   ├── BallotType.php
│   │   ├── ElectorateType.php
│   │   └── VoteVisibility.php
│   ├── Events/
│   │   ├── BallotPublished.php
│   │   ├── BallotClosed.php
│   │   └── VoteCast.php
│   ├── Http/
│   │   └── Controllers/
│   │       ├── BallotsController.php
│   │       └── VotingSettingsController.php
│   ├── Livewire/
│   │   ├── Ballots/
│   │   │   ├── Index.php
│   │   │   ├── Show.php
│   │   │   ├── VoteForm.php
│   │   │   ├── Create.php
│   │   │   └── Results.php
│   │   └── Settings/
│   │       └── VotingSettings.php
│   ├── Models/
│   │   ├── Ballot.php
│   │   ├── BallotOption.php
│   │   ├── BallotResponse.php
│   │   ├── ProxyVote.php
│   │   └── TeamVotingSetting.php
│   ├── Policies/
│   │   ├── BallotPolicy.php
│   │   └── ProxyVotePolicy.php
│   ├── Providers/
│   │   └── VotingServiceProvider.php
│   ├── Resolvers/
│   │   └── DefaultUserVoterEligibilityResolver.php
│   ├── Services/
│   │   ├── BallotTallyService.php
│   │   └── QuorumService.php
│   └── Support/
│       └── VoterUnit.php (value object / helper)
└── tests/
    ├── Feature/
    └── Unit/
```

## 5. Data model

### 5.1 ballots

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| team_id | FK → teams | Required, indexed |
| created_by_user_id | FK → users | Who created the ballot |
| title | string | |
| description | text nullable | |
| type | string/enum | poll, resolution, election |
| status | string/enum | draft, scheduled, open, closed, cancelled |
| electorate | string/enum | all_members, council, custom |
| vote_visibility | string/enum | secret, visible_after_close, visible_realtime |
| allow_abstain | boolean | default false |
| allow_multiple_selections | boolean | default false (elections may differ) |
| quorum_percent | decimal nullable | e.g. 50.00 — null = no quorum requirement |
| quorum_basis | string nullable | eligible_units, eligible_users |
| opens_at | timestamp nullable | |
| closes_at | timestamp nullable | |
| published_at | timestamp nullable | |
| closed_at | timestamp nullable | |
| settings | json nullable | Extensibility (e.g. require 2/3 majority) |
| timestamps | | |
| soft_deletes | optional | Recommend yes for audit trail |

**Indexes:** `(team_id, status)`, `(team_id, opens_at, closes_at)`

### 5.2 ballot_options

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| ballot_id | FK → ballots | cascade delete |
| label | string | e.g. "Yes", "No", candidate name |
| description | text nullable | |
| sort_order | integer | default 0 |
| timestamps | | |

For yes/no resolutions, seed two options on publish if none exist.

### 5.3 ballot_responses (critical table)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| ballot_id | FK → ballots | |
| ballot_option_id | FK → ballot_options | |
| cast_by_user_id | FK → users | Who physically submitted |
| voter_unit_type | string nullable | Morph class, e.g. `App\Models\User` or `App\Models\Property` |
| voter_unit_id | bigint nullable | Morph id |
| proxy_vote_id | FK nullable → proxy_votes | If cast via proxy |
| ip_address | string nullable | Audit |
| user_agent | string nullable | Audit |
| cast_at | timestamp | |
| timestamps | | |

**Unique constraint (non-negotiable):**

```
UNIQUE (ballot_id, voter_unit_type, voter_unit_id)
```

This prevents double voting when designated voter changes.

Default resolver behavior: `voter_unit_type = App\Models\User`, `voter_unit_id = user.id`.

### 5.4 proxy_votes

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| team_id | FK → teams | |
| ballot_id | FK nullable → ballots | null = all ballots in date range / meeting |
| grantor_voter_unit_type | string | Property or User morph |
| grantor_voter_unit_id | bigint | |
| proxy_holder_user_id | FK → users | Person exercising the proxy |
| granted_by_user_id | FK → users | Owner who authorized |
| valid_from | timestamp | |
| valid_until | timestamp nullable | |
| revoked_at | timestamp nullable | |
| timestamps | | |

**Indexes:** `(ballot_id, grantor_voter_unit_type, grantor_voter_unit_id)` — one active proxy per unit per ballot.

### 5.5 team_voting_settings

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| team_id | FK unique → teams | |
| default_quorum_percent | decimal nullable | |
| default_vote_visibility | string | |
| allow_proxy_votes | boolean | default true |
| lock_designation_during_open_ballots | boolean | default false (app-level, but store team pref) |
| timestamps | | |

## 6. Enums

- **BallotStatus:** draft, scheduled, open, closed, cancelled
- **BallotType:** poll, resolution, election
- **ElectorateType:** all_members, council, custom
- **VoteVisibility:** secret, visible_after_close, visible_realtime
- **QuorumBasis:** eligible_units, eligible_users

Use backed string enums (PHP 8.1+).

## 7. Extension contract: VoterEligibilityResolver

**Interface:** `Afterburner\Voting\Contracts\VoterEligibilityResolver`

**Required methods:**

```php
/**
 * Return voter units this user may cast votes for on this ballot.
 * Each item must be identifiable by (type, id).
 */
public function eligibleVoterUnits(User $user, Ballot $ballot): Collection;

/**
 * Return total eligible voter units for quorum denominator.
 */
public function totalEligibleVoterUnits(Ballot $ballot): int;

/**
 * Check if user may cast for a specific voter unit right now.
 */
public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool;

/**
 * Human-readable label for a voter unit (UI: "Lot 14", "Jane Smith").
 */
public function voterUnitLabel(string $voterUnitType, int $voterUnitId): string;
```

**Default implementation:** `DefaultUserVoterEligibilityResolver`

- Eligible unit = the user themselves (`App\Models\User`, `$user->id`)
- `totalEligibleVoterUnits` = count of team members with `vote_resolutions` permission
- Used when host app has no property model

**Config binding:**

```php
'eligibility_resolver' => env(
    'AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER',
    \Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver::class
),
```

Register as singleton in service provider. Validate class implements interface on boot.

**Strata app (out of package scope, document in README):** App implements resolver checking designated voter + active proxy against Property model.

## 8. Permissions

### 8.1 Reuse existing template slugs

Do not invent new slugs for core voting. Use:

- `vote_resolutions` — cast votes, view open ballots assigned to user
- `create_resolutions` — create/edit/publish ballots

### 8.2 Add package-specific slugs via VotingPermissionsSeeder

Mirror `DocumentPermissionsSeeder` pattern (`insertOrIgnore`, assign to team owner highest-hierarchy role):

| Slug | Name | Purpose |
|------|------|---------|
| manage_ballots | Manage Ballots | Full admin: edit others' drafts, cancel, force-close |
| view_ballot_results | View Ballot Results | View results before/after close based on visibility rules |
| manage_proxy_votes | Manage Proxy Votes | Grant/revoke proxies (may overlap with app logic) |
| export_ballot_results | Export Ballot Results | Download CSV/PDF of results |

Map in RoleTemplates strata template:

- president, secretary: add `manage_ballots`, `export_ballot_results`
- council_member: add `view_ballot_results`
- strata_owner: keep `vote_resolutions` only

Seeder must be idempotent.

## 9. Policies

### 9.1 BallotPolicy

| Method | Logic |
|--------|-------|
| viewAny | User belongs to team |
| view | Belongs to team + (`vote_resolutions` OR `create_resolutions` OR `manage_ballots`) |
| create | Belongs to team + `create_resolutions` |
| update | Draft/scheduled only + (`create_resolutions` and owns ballot OR `manage_ballots`) |
| publish | Same as update |
| close | Open ballot + (`manage_ballots` OR creator with `create_resolutions`) |
| vote | Open ballot + `vote_resolutions` + resolver returns ≥1 eligible unit not yet voted |
| viewResults | Closed ballot OR visibility allows + appropriate permission |
| delete | Draft only + `manage_ballots` |

Always verify `$ballot->team_id === $user->currentTeam->id` (or route-bound team).

### 9.2 ProxyVotePolicy

- **Grant:** user represents grantor unit OR has `manage_proxy_votes`
- **Revoke:** grantor, proxy holder, or admin
- **Exercise:** proxy holder during valid window on open ballot

## 10. Actions (business logic)

Keep controllers/Livewire thin. All mutations go through Actions.

### CreateBallot

Input: team, user, title, description, type, electorate, options[], schedule, quorum settings

Creates ballot in draft status. Validates team membership + `create_resolutions`.

### PublishBallot

Transition: draft|scheduled → open (or scheduled if future `opens_at`)

Validates at least 2 options (or 1 for single-choice poll with abstain). Fires `BallotPublished` event. Optionally notify eligible voters (hook/event only in v1; notification implementation optional).

### CastVote (most critical)

**Preconditions (all must pass):**

1. Ballot status is open
2. Now between `opens_at` and `closes_at` (if set)
3. `$this->authorize('vote', $ballot)`
4. `$resolver->canCastVote($user, $ballot, $unitType, $unitId)`
5. No existing `BallotResponse` for `(ballot_id, voter_unit_type, voter_unit_id)` — use DB transaction + unique constraint
6. If `proxy_vote_id` provided, proxy is valid and not revoked

**Write:**

```php
BallotResponse::create([
    'ballot_id' => ...,
    'ballot_option_id' => ...,
    'cast_by_user_id' => $user->id,
    'voter_unit_type' => $unitType,
    'voter_unit_id' => $unitId,
    'proxy_vote_id' => $proxy?->id,
    'cast_at' => now(),
]);
```

On unique constraint violation: Return friendly error "This voting unit has already cast a vote."

Fire `VoteCast` event (for audit log integration in host app).

### CloseBallot

Transition open → closed, set `closed_at`. Fire `BallotClosed`. Trigger tally snapshot if needed.

### BallotTallyService

Count responses per option. Return percentages based on votes cast and optionally eligible units. Respect `vote_visibility`: hide option breakdown until closed if configured. Support `allow_abstain`.

### QuorumService

- **Numerator:** distinct voter units with responses (or users, per `quorum_basis`)
- **Denominator:** `$resolver->totalEligibleVoterUnits($ballot)`
- **Return:** `{ met: bool, percent: float, required: float, cast: int, eligible: int }`

## 11. Electorate rules

Implement electorate filtering in resolver or a dedicated `ElectorateFilter`:

| Electorate | Eligible users/units |
|------------|---------------------|
| all_members | All team members with `vote_resolutions` (default resolver: each user) |
| council | Users with any of: president, treasurer, secretary, council_member roles |
| custom | Delegates entirely to config callback or app-registered custom electorate class |

**Config:**

```php
'council_role_slugs' => ['president', 'treasurer', 'secretary', 'council_member'],
'custom_electorate_resolver' => null, // FQCN optional
```

For council ballots in generic apps, filter eligible units to council role holders only.

## 12. Livewire components

Register in service provider (mirror documents naming):

| Alias | Class | Purpose |
|-------|-------|---------|
| voting.index | Ballots\Index | List ballots: open, upcoming, closed |
| voting.show | Ballots\Show | Ballot detail + vote status for current user |
| voting.vote-form | Ballots\VoteForm | Cast vote UI — one form per eligible voter unit |
| voting.create | Ballots\Create | Create/edit draft ballot |
| voting.results | Ballots\Results | Tally + quorum display |
| voting.settings.voting-settings | Settings\VotingSettings | Team defaults |

### UI requirements

**Index:**

- Tabs: Open / Upcoming / Closed
- Badge: "Action required" if user has eligible units not yet voted
- Permission-gated create button

**Vote form:**

- If user eligible for multiple units (strata: multiple lots), show separate card per unit
- If unit already voted, show read-only confirmation (who cast, when, which option — per visibility rules)
- If voting via proxy, indicate proxy source
- Submit calls `CastVote` action

**Create:**

- Title, description, type, electorate dropdown
- Dynamic option rows (add/remove/reorder)
- Schedule: opens_at, closes_at
- Quorum percent optional
- Save draft / Publish buttons

**Results:**

- Bar chart or table per option
- Quorum widget: "23 of 40 eligible (57.5%) — quorum met"
- Export button if `export_ballot_results`
- For secret ballots after close: show counts, not who voted for what (unless visibility allows)

Use existing Afterburner/Tailwind patterns from documents views.

**Buttons (host app components — do not use raw `bg-indigo-*` button classes):**

| Role | Component | Examples |
|------|-----------|----------|
| Primary action | `<x-button>` | Create Ballot, Save & Publish, Publish Ballot, Submit Vote |
| Secondary action | `<x-secondary-button>` | Save Draft, Close Ballot, View Results, Filters-style actions |
| Destructive action | `<x-danger-button>` | Delete ballot (Phase 2+) |
| Inline row action | Icon-only `<button>` + `title` | Remove ballot option, edit/delete row actions (match documents index) |
| Text / navigation link | Plain anchor | `text-gray-700 hover:text-indigo-600` (see documents breadcrumbs) |

Use `no-spinner` on Livewire-triggered buttons (matches documents). For cross-page navigation that should look like a button, prefer `wire:click` + `$this->redirectRoute(...)` with `<x-button>` rather than styled `<a>` tags.

**Inline row actions:** Do not use visible text such as "Remove" on compact list/row controls. Use Heroicons-style inline SVG (as in `afterburner-documents` `documents/index.blade.php`), `class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded"` for destructive row actions, and a descriptive `title` for accessibility.

Form inputs keep documents focus styles (`focus:border-indigo-500 focus:ring-indigo-500`). Progress/tally bars use primary gray (`bg-gray-800` / `dark:bg-gray-200`), not indigo fills.

## 13. Routes

File: `routes/web.php`

```php
Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/teams/{team}/ballots', [BallotsController::class, 'index'])
        ->name('teams.ballots.index');
    Route::get('/teams/{team}/ballots/create', [BallotsController::class, 'create'])
        ->name('teams.ballots.create')
        ->middleware('can:create,Afterburner\Voting\Models\Ballot,team');
    Route::get('/teams/{team}/ballots/{ballot}', [BallotsController::class, 'show'])
        ->name('teams.ballots.show');
    Route::get('/teams/{team}/ballots/{ballot}/results', [BallotsController::class, 'results'])
        ->name('teams.ballots.results');
    Route::get('/teams/{team}/voting-settings', VotingSettingsController::class)
        ->name('teams.voting-settings')
        ->middleware('can:update,team');
});
```

Always scope route model binding: verify `$ballot->team_id === $team->id` in controller or use custom binding.

Route prefix/name pattern must match documents: `teams.{feature}.{action}`.

## 14. Service provider

`VotingServiceProvider` must:

- Guard boot with `class_exists(\App\Models\Team::class)`
- `mergeConfigFrom` → `afterburner-voting`
- Publish config, migrations, views with standard tags
- `loadMigrationsFrom`, `loadRoutesFrom`, `loadViewsFrom('afterburner-voting')`
- Register Livewire components
- Register policies via `Gate::policy()`
- Bind `VoterEligibilityResolver` singleton from config
- Register navigation:

```php
Navigation::register([
    'label' => 'Voting',
    'route' => 'teams.ballots.index',
    'route_params' => fn() => ['team' => auth()->user()?->currentTeam?->id],
    'icon' => 'check-badge', // or similar
    'order' => 25,
    'permission' => fn($user) => $user?->can('viewAny', Ballot::class),
    'active' => fn() => request()->routeIs('teams.ballots.*'),
]);
```

- Optionally register team settings nav item (like documents does for system settings)
- Register `afterburner:voting:install` command
- Register enums/events for audit integration if host app listens

## 15. Config file

`config/afterburner-voting.php`:

```php
return [
    'enabled' => env('AFTERBURNER_VOTING_ENABLED', true),
    'eligibility_resolver' => env(
        'AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER',
        \Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver::class
    ),
    'council_role_slugs' => [
        'president', 'treasurer', 'secretary', 'council_member',
    ],
    'default_vote_visibility' => env('AFTERBURNER_VOTING_DEFAULT_VISIBILITY', 'visible_after_close'),
    'default_quorum_percent' => env('AFTERBURNER_VOTING_DEFAULT_QUORUM', null),
    'allow_proxy_votes' => env('AFTERBURNER_VOTING_ALLOW_PROXY', true),
    'audit' => [
        'skip_routes' => [], // merge into host audit.skip_routes if present
    ],
];
```

## 16. HasVoting trait (for Team model)

```php
trait HasVoting
{
    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class, 'team_id');
    }

    public function votingSettings(): HasOne
    {
        return $this->hasOne(TeamVotingSetting::class, 'team_id');
    }

    public function openBallots(): HasMany
    {
        return $this->ballots()->where('status', BallotStatus::Open);
    }
}
```

Document in README: add trait to `App\Models\Team` in host app (optional but recommended). Same pattern as `HasDocuments`.

## 17. Install command

`php artisan afterburner:voting:install`

Steps (mirror documents):

1. Publish config (`--tag=afterburner-voting-config --force`)
2. Publish migrations (`--tag=afterburner-voting-migrations --force`)
3. Publish views (`--tag=afterburner-voting-assets --force`)
4. Append env vars to `.env` and `.env.example`:
   - `AFTERBURNER_VOTING_ENABLED=true`
   - `AFTERBURNER_VOTING_DEFAULT_VISIBILITY=visible_after_close`
   - `AFTERBURNER_VOTING_ALLOW_PROXY=true`
5. Prompt: run migrations?
6. Prompt: seed voting permissions?
7. Print next steps (add `HasVoting` trait, configure resolver for strata apps)

## 18. Security invariants (must have tests)

Write explicit tests for each:

1. One vote per voter unit per ballot — unique constraint enforced; second cast returns error
2. Designated voter swap does not allow re-vote — User A votes for Property X, designation changes to User B, User B cannot vote Property X on same ballot
3. Proxy cannot double-vote — grantor unit already voted → proxy holder blocked
4. Closed ballot rejects votes — status !== open
5. Wrong team rejected — ballot.team_id mismatch → 404/403
6. No permission → no vote — user without `vote_resolutions` cannot cast
7. Council electorate excludes plain owners — owner role user not in council ballot eligible set
8. Quorum math — correct numerator/denominator with multi-unit resolver (use test fake resolver)
9. Visibility rules — secret ballot hides per-user choices until closed
10. Race condition — two simultaneous casts for same unit: one succeeds, one fails (transaction test)

## 19. Test setup

Use Orchestra Testbench (copy documents package test harness):

- Fixture `App\Models\User`, `App\Models\Team` in `tests/Fixtures/Models/`
- Migrate host + package migrations
- Seed roles/permissions in test setup
- Feature tests for full vote flow
- Unit tests for `TallyService`, `QuorumService`, resolver

Minimum test count target: ~25–30 tests for v1.

## 20. Integration with host strata app (document in README, do not build in package)

The strata consumer app (strata project) will later:

1. Create Property model (`team_id`, `lot_number`, `designated_voter_id`)
2. Create PropertyStakeholder pivot (`property_id`, `user_id`, `role`)
3. Implement `PropertyVoterEligibilityResolver implements VoterEligibilityResolver`
4. Set `AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER=App\Strata\Voting\PropertyVoterEligibilityResolver`
5. Add `HasVoting` to Team
6. Wire designation UI (co-owners pick voter for their lot)
7. Optionally lock designation changes while team has open ballots

Package README must include this "Strata integration" section with example resolver skeleton.

## 21. Phased delivery plan

### Phase 1 — MVP (ship first)

- Migrations: ballots, options, responses
- Default user resolver (one person one vote)
- CRUD draft ballots, publish, close
- Cast vote with unique constraint
- Basic tally
- BallotPolicy + permissions seeder
- Index, Show, VoteForm, Create, Results Livewire
- Install command
- Core tests (invariants 1–6)

### Phase 2 — Governance features

- QuorumService + UI widget
- Proxy votes (grant, revoke, cast via proxy)
- Electorate: council vs all_members
- Export results (CSV)
- Vote visibility modes
- Events for audit log

### Phase 3 — AGM polish (can defer)

- Attendance tracking (present/proxy/eligible) — may belong in future meetings package
- PDF ballot generation
- Scheduled auto-open/auto-close (queued jobs)
- Revoke vote before close (config-gated, audit-heavy)
- Weighted votes (unit entitlement) via resolver extension

## 22. Explicitly out of scope for voting package v1

- Property/Lot/Ownership models (strata app concern)
- Designated voter UI (strata app concern)
- Meeting scheduling (future meetings package)
- Email notifications (host app or future communications package — provide events only)
- Bylaw document linking — use `laravel-afterburner/documents` (see Document attachments below)
- Legal compliance certification / BC Strata Property Act specific rules

## 23. Reference files to read before coding

In **afterburner-documents**:

- `src/Providers/DocumentsServiceProvider.php` — full integration pattern
- `src/Policies/DocumentPolicy.php` — permission check pattern
- `src/Database/Seeders/DocumentPermissionsSeeder.php` — seeder pattern
- `src/Console/Commands/InstallCommand.php` — install flow
- `routes/web.php` — team-scoped routing
- `src/Concerns/HasDocuments.php` — Team trait pattern

In **strata (host template)**:

- `app/Support/RoleTemplates.php` — existing permission slugs
- `app/Support/Navigation.php` — nav registry API
- `app/Traits/HasAfterburnerRoles.php` — `hasPermission()` usage

In **afterburner-documents/AFTERBURNER_PACKAGE_PLAN.md**:

- Add-on package conventions (sections on service providers, publish tags, env vars)

## 24. Acceptance criteria (definition of done)

- [x] `composer require laravel-afterburner/voting` works via path repo
- [x] `php artisan afterburner:voting:install` completes without errors
- [x] Navigation item appears for users with voting access
- [x] User with `create_resolutions` can create and publish a yes/no ballot
- [x] User with `vote_resolutions` can cast one vote on open ballot
- [x] Second vote attempt by same user (or swapped designated voter in test fake) is rejected
- [x] Results page shows tally after close
- [x] Quorum displays correctly when configured
- [x] All package tests pass in isolation via Testbench
- [x] README documents strata resolver integration
- [x] Code follows documents package conventions (Pint clean, matching directory layout)

## 25. Suggested first message for the dev chat

Build `laravel-afterburner/voting` as an Afterburner add-on package following the exact patterns in `afterburner-documents`. The package provides team-scoped ballots and vote casting with a pluggable `VoterEligibilityResolver` contract. Default behavior is one vote per user; strata apps will later plug in a property-based resolver. Critical invariant: `ballot_responses` has a unique constraint on `(ballot_id, voter_unit_type, voter_unit_id)` so reassignment of designated voters cannot allow double voting. Use existing permission slugs `vote_resolutions` and `create_resolutions`. Reference the full spec above for data model, policies, actions, Livewire UI, and phased delivery.
