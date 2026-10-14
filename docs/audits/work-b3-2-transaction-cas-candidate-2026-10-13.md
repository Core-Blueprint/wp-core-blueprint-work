# Work B3.2 Transaction & Status CAS: Batch Candidate

Source baseline: `95e4386025f30f8b2ca3bc9051c3d16f8e0b7bd0`.

- B3.2a Recurrence Rules: savepoint-aware transaction ownership; validate SQL begin/release/commit and rollback failures; assignments reuse their caller transaction. Rule update holds a row lock.
- B3.2b Billing Units: prepare and external link mutations use the same Work SQL transaction boundary. Existing fingerprint/row locking and identity uniqueness are preserved. External status projection remains last-write-wins until versioned external events are contractually specified.
- B3.2c Frontend Actions: require the UI-observed source status, reject missing/stale preconditions, pass expected source to the locked A1 Work Item repository. Public PHP actions retain their original two-argument entrypoint while using the observed source as a repository CAS guard and accept an optional third expected status.

## Limits and operator tests

- `WorkSqlTransaction::run()` provides SQL transaction/savepoint ownership for InnoDB tables, not rollback of arbitrary WordPress hooks or remote side effects.
- An existing caller transaction still owns the final commit. Early notification of persistence to external consumers remains outside this local SQL guarantee.
- Frontend Actions is a PHP domain contract, not an HTTP route. Transport integrations own nonce/CSRF.
- Integration fixture uses the isolated Work MariaDB test database and tests nested savepoint ownership, fail rollback, exception rollback and frontend snapshot conflicts. Source smoke enforces both production callers. More end-to-end Billing/Recurrence scenarios can be added if targeted integration exposes a gap.
- No remote merge, CI or deployment is implied by this candidate. Golden closure needs passing operator Level 1 / Level 2 / Level 3, ZIP-integrity, checksum and sandbox acceptance.
