# Changelog

All notable changes to `laravel-afterburner/voting` are documented in this file.

## [1.0.0] - 2026-05-27

First stable release (Phases 1–4).

### Added

- Ballots, options, and responses with unique `(ballot_id, voter_unit_type, voter_unit_id)` constraint
- Default user eligibility resolver and pluggable `VoterEligibilityResolver` contract
- Draft/create/publish/close ballot flow with double-vote prevention
- Basic tally, results UI, and permissions seeder (`vote_resolutions`, `create_resolutions`, package slugs)
- Quorum tracking and UI widget; council vs all-members electorate
- Proxy votes (grant, revoke, cast via proxy); vote visibility modes
- CSV and PDF results export; weighted vote tally via `ProvidesWeightedVotes`
- Vote revocation (config-gated); scheduled auto-open/auto-close via queued jobs
- Voting audit events (`BallotPublished`, `BallotClosed`, `VoteCast`, etc.)
- `team_voting_settings` and Voting Settings page (`/teams/{team}/voting-settings`)
- Team defaults wired into `CreateBallot`, `PublishBallot`, and `CreateProxy`
- Boot-time validation for `AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER`
- `SendBallotPublishedVoterNotifications` listener stub for host-app hooks
- Optional document attachments via documents package (`ballot-documents` Livewire)
- `afterburner:voting:install` command and team navigation registration

### Changed

- New ballot form pre-fills quorum and visibility from team settings
