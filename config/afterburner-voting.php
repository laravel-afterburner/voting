<?php

use Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver;

return [

    'enabled' => true,

    'eligibility_resolver' => DefaultUserVoterEligibilityResolver::class,

    /*
    | When set, council electorate and filters read slugs from this class (e.g.
    | App\Support\CouncilRoles) instead of the static list below.
    */
    'council_role_resolver' => env('AFTERBURNER_COUNCIL_ROLE_RESOLVER', \App\Support\CouncilRoles::class),

    'council_role_slugs' => [
        'president',
        'treasurer',
        'secretary',
        'council_member',
    ],

    'custom_electorate_resolver' => null,

    'default_vote_visibility' => 'secret',

    'default_quorum_percent' => null,

    /*
    | When set, every lot uses this vote weight and per-lot weight fields are hidden
    | in the host application. Leave null to allow individual vote weights per lot.
    */
    'default_vote_weight_per_lot' => null,

    'allow_proxy_votes' => true,

    /*
    | Class implementing ProxyGrantResolver for proxy management UI.
    | When null, the proxy route returns 404 and no proxy nav item is shown.
    */
    'proxy_grant_resolver' => null,

    'allow_vote_revocation' => false,

    'schedule_transitions' => true,

    'documents_enabled' => true,

    'audit' => [
        'skip_routes' => [],
    ],

];
