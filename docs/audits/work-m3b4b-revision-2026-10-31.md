# M3-B4-B | Canonical recurrence revision foundation (2026-10-31)

Previous transport validation: 71 WordPress tests / 1690 assertions and release ZIP checksum 5373ad32183e608f04cada63b0a24a440f59a1ad6dcd05187b5d4d4c594bca28 (source a45c082ecc1ed8132fe44836837b9ee8c52a5261).

This patch is **SOURCE, not validated**. Work schema 2.4 → 2.5 adds an integer revision to the existing recurrence rules table with a safe default of 1 for migrated rows. Canonical native updates, trash, restore, scheduler cursor, and bulk project-context sync advance revision; Workspace detail exposes it. A version-checked repository write reuses the existing transaction and row lock, returns 409 on an outdated expected revision and leaves the canonical write untouched. Native writer signatures and fallbacks are preserved. The new API still has **no REST mutation route** until schema migration and tests are proven in real WordPress.

Evidence required: PHP syntax and targeted smoke, all Level 1, WordPress/MariaDB Level 2 with existing 2.4→2.5 migration and safe data, release ZIP/SHA, sandbox native create/edit/trash/restore and scheduler regression. Protected renderer must remain byte identical. No merge, public release, CI, or native retirement.
