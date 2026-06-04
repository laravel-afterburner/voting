<?php

use Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver;

return [

    'enabled' => true,

    'eligibility_resolver' => DefaultUserVoterEligibilityResolver::class,

    'council_role_slugs' => [
        'president',
        'treasurer',
        'secretary',
        'council_member',
    ],

    'custom_electorate_resolver' => null,

    'default_vote_visibility' => 'secret',

    'default_quorum_percent' => null,

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
