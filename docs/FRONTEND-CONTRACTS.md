# Core Blueprint Work — Frontend Contracts

D3 defines the builder-neutral frontend boundary for Core Blueprint Work. These PHP contracts are owned by Work and remain usable without Bricks or any other page builder.

## Scope

D3 exposes frontend-safe contracts for:

- Services;
- Projects;
- Work Items.

D3 does not expose Time Entries, timers, recurrence rules/occurrences, CRM customer references, billing classifications, source relations or private assignment data as frontend resources.

## Authorization

`CB\Work\Frontend\Access` is the canonical frontend authorization boundary.

Canonical Work content remains private by default. Users with `cb_manage_work` may resolve records for authenticated preview/administration. Other users may read only published, non-password-protected records that are explicitly opted in through:

`cb_work_frontend_can_read( bool $allowed, WP_Post $post, string $post_type, int $actor_user_id )`

The default is `false`.

A non-manager Work Item mutation requires a second explicit opt-in:

`cb_work_frontend_can_transition_work_item( bool $allowed, WP_Post $post, string $target_status, int $actor_user_id )`

The actor must also be logged in and have read access to the Work Item. The default is `false`.

## Data

Frontend-safe projections live under `CB\Work\Frontend\Data`:

- `Data\Service::get()`;
- `Data\Project::get()`;
- `Data\WorkItem::get()`.

A denied or unavailable resource returns `WP_Error( 'work_resource_unavailable' )`.

Work Item frontend data contains operational presentation fields such as title, description, Project/Service/Work Type IDs, priority, estimate, planning dates and Work status. It intentionally omits customer references, billing data, assignments and integration/source metadata.

## Queries

Builder-neutral queries live under `CB\Work\Frontend\Queries`:

- `Queries\Services::query()`;
- `Queries\Projects::query()`;
- `Queries\WorkItems::query()`.

Queries are bounded and always re-project each candidate through the Data layer, so query membership never bypasses record-level frontend authorization. Returned `count` is only the number of authorized items returned; it is not a hidden total of private records.

Work Item queries reuse the canonical D2 Work Item search engine. The frontend query intentionally does not expose customer, billing or assignee filter dimensions.

## Conditions

Pure boolean conditions live under `CB\Work\Frontend\Conditions\Resources` and include resource availability, Work Item status/priority, Project/Service relation and transition availability.

Conditions never persist data and return `false` when a resource is not frontend-authorized.

## Actions

`CB\Work\Frontend\Actions\WorkItems::transition_status()` is the D3 governed mutation contract.

It:

- validates the canonical Work lifecycle transition;
- uses `Frontend\Access` for action authorization;
- delegates persistence to `Repository\WorkItems::transition_status()`;
- records the existing `work.item.status.changed` governance event;
- returns the updated frontend-safe Work Item projection.

D3 actions are PHP domain contracts, not HTTP endpoints. A builder or other transport adapter owns nonce/CSRF and request transport; authorization and business rules remain inside Work.

## Builder adapters

D4 may map these contracts into Bricks dynamic data, query loops, conditions and actions. The Bricks adapter must remain thin: it may translate Bricks input/output shapes and provide transport security, but it must not reimplement Work authorization, query semantics, lifecycle rules or persistence.

Future builders must be able to consume the same D3 contracts without changes to the Work domain model.
