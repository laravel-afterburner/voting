<?php

use Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver;
use App\Support\CouncilRoles;

return [

    /*
    |--------------------------------------------------------------------------
    | Package toggle
    |--------------------------------------------------------------------------
    |
    | When false, voting routes, navigation, and Livewire components are not
    | registered. Existing ballot data remains in the database.
    |
    */
    'enabled' => env('AFTERBURNER_VOTING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Voter eligibility resolver
    |--------------------------------------------------------------------------
    |
    | Determines what each vote represents and who may cast for which units.
    |
    | DefaultUserVoterEligibilityResolver — one vote per user (generic apps).
    |
    | Strata / property apps should bind a custom resolver, e.g.
    | App\Strata\Voting\PropertyVoterEligibilityResolver, so votes attach to
    | lots rather than users. Configure in the host app's published config;
    | do not rely on .env for resolver class names in fixed installs.
    |
    | The resolver receives the ballot on every call so host apps can branch
    | on electorate (owner vs council) or ballot type.
    |
    */
    'eligibility_resolver' => env(
        'AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER',
        DefaultUserVoterEligibilityResolver::class,
    ),

    /*
    |--------------------------------------------------------------------------
    | Council role resolution
    |--------------------------------------------------------------------------
    |
    | Used when a ballot's electorate is "council". Host apps may bind a class
    | (e.g. App\Support\CouncilRoles) that returns team-specific council slugs.
    |
    | council_role_slugs — fallback when council_role_resolver is null.
    |
    */
    'council_role_resolver' => env('AFTERBURNER_COUNCIL_ROLE_RESOLVER', class_exists(CouncilRoles::class) ? CouncilRoles::class : null),

    'council_role_slugs' => [
        'president',
        'treasurer',
        'secretary',
        'council_member',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom electorate
    |--------------------------------------------------------------------------
    |
    | Class implementing CustomElectorateResolver. Required when publishing
    | ballots with electorate = custom. Validated on boot when set.
    |
    */
    'custom_electorate_resolver' => env('AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER'),

    /*
    |--------------------------------------------------------------------------
    | Package-level defaults (overridden per team in System Settings → Voting)
    |--------------------------------------------------------------------------
    |
    | default_vote_visibility — secret | visible_after_close | visible_realtime
    | default_quorum_percent — null = no quorum requirement on new ballots
    | default_vote_weight_per_lot — when set, every lot uses this weight at
    |     tally time and host apps should hide per-lot weight fields. Leave
    |     null to use per-lot entitlement from the host property register
    |     (requires ProvidesWeightedVotes on the eligibility resolver).
    |
    */
    'default_vote_visibility' => env('AFTERBURNER_VOTING_DEFAULT_VOTE_VISIBILITY', 'secret'),

    'default_quorum_percent' => env('AFTERBURNER_VOTING_DEFAULT_QUORUM', null),

    'default_vote_weight_per_lot' => env('AFTERBURNER_VOTING_DEFAULT_VOTE_WEIGHT_PER_LOT', null),

    /*
    |--------------------------------------------------------------------------
    | Proxy votes
    |--------------------------------------------------------------------------
    |
    | allow_proxy_votes — global kill switch; teams can further disable in
    |     System Settings → Voting.
    |
    | proxy_grant_resolver — class implementing ProxyGrantResolver for the
    |     proxy management UI. When null, proxy routes and nav are hidden.
    |
    */
    'allow_proxy_votes' => env('AFTERBURNER_VOTING_ALLOW_PROXY_VOTES', true),

    'proxy_grant_resolver' => env('AFTERBURNER_VOTING_PROXY_GRANT_RESOLVER'),

    /*
    |--------------------------------------------------------------------------
    | Ballot behaviour
    |--------------------------------------------------------------------------
    |
    | allow_vote_revocation — voters may withdraw a vote (no re-cast).
    | schedule_transitions — auto open/close on published schedule.
    | documents_enabled — ballot document attachments (afterburner-documents).
    |
    */
    'allow_vote_revocation' => env('AFTERBURNER_VOTING_ALLOW_VOTE_REVOCATION', false),

    'schedule_transitions' => env('AFTERBURNER_VOTING_SCHEDULE_TRANSITIONS', true),

    'documents_enabled' => env('AFTERBURNER_VOTING_DOCUMENTS_ENABLED', true),

    'audit' => [
        'skip_routes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI customization (host app overrides)
    |--------------------------------------------------------------------------
    |
    | vote_cast_panel_classes — Tailwind classes for the "Your vote" panel on
    |     the ballot show page. Host apps can match their navigation chrome,
    |     e.g. 'bg-landing-mist dark:bg-[#2e2a24]' in Strata.
    |
    */
    'ui' => [
        'vote_cast_panel_classes' => 'bg-gray-50 dark:bg-gray-900/30',
    ],

];
