<?php

use Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver;

return [

    'enabled' => env('AFTERBURNER_VOTING_ENABLED', true),

    'eligibility_resolver' => env(
        'AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER',
        DefaultUserVoterEligibilityResolver::class
    ),

    'council_role_slugs' => [
        'president',
        'treasurer',
        'secretary',
        'council_member',
    ],

    'custom_electorate_resolver' => env('AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER', null),

    'default_vote_visibility' => env('AFTERBURNER_VOTING_DEFAULT_VISIBILITY', 'visible_after_close'),

    'default_quorum_percent' => env('AFTERBURNER_VOTING_DEFAULT_QUORUM', null),

    'allow_proxy_votes' => env('AFTERBURNER_VOTING_ALLOW_PROXY', true),

    /*
    | Class implementing ProxyGrantResolver for proxy management UI.
    | When null, the proxy route returns 404 and no proxy nav item is shown.
    */
    'proxy_grant_resolver' => env('AFTERBURNER_VOTING_PROXY_GRANT_RESOLVER'),

    'allow_vote_revocation' => env('AFTERBURNER_VOTING_ALLOW_VOTE_REVOCATION', false),

    'schedule_transitions' => env('AFTERBURNER_VOTING_SCHEDULE_TRANSITIONS', true),

    'documents_enabled' => env('AFTERBURNER_VOTING_DOCUMENTS_ENABLED', true),

    'audit' => [
        'skip_routes' => [],
    ],

];
