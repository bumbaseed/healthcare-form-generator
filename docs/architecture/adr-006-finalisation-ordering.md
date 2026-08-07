# ADR-006: Write the active flag last and make every prior step idempotent

**Status:** Accepted
**Recorded:** 2026-08-07 (retrospectively, from the implementation)

## Context

Finalising a form does four things that can each fail independently:

1. `CREATE TABLE` for the form's data ([ADR-001](adr-001-per-form-tables.md))
2. write the generated PHP file to `forms/`
3. create a git branch containing that file ([ADR-003](adr-003-git-plumbing-for-form-branches.md))
4. set `is_active = true` on the definition row

These span three systems: PostgreSQL DDL, the filesystem, and git; and cannot
be wrapped in a single transaction. PostgreSQL can roll back its own DDL, but it
cannot un-write a file or un-create a branch.

So partial failure is a certainty to be designed _for_. The question is only which partial states are observable,
and whether the operation can be safely retried from any of them.

The state that must never exist is a form that appears usable but isn't, listed
for staff to complete, with no table behind it to write answers into.

## Decision

Make `is_active` the commit point. It is written last, only after every other
step has succeeded, and every preceding step is made idempotent so the whole
operation is safe to re-run from any failure:

| Step              | What makes a retry safe                                                                  |
| ----------------- | ---------------------------------------------------------------------------------------- |
| Create the table  | An orphaned table from a previous failed attempt is dropped before recreating            |
| Write the file    | `file_put_contents` overwrites unconditionally                                           |
| Create the branch | An existing branch raises `GitBranchExistsException`, which the caller treats as success |

A form is therefore in exactly one of two states: **fully finalised**, or **not
finalised and safe to re-run**. There is no third state.

## Consequences

**Positive**

- **No partial-success state is user-visible.** Form selection lists active
  forms only, so a failed finalisation is invisible to staff rather than
  presented as broken. Nobody can start completing a form whose table doesn't
  exist.
- **Retry needs no manual cleanup.** The admin presses Save again. There is no
  runbook step to drop a stale table or delete a half-made branch first.
- **Diagnosis is direct.** A definition row sitting at `is_active = false` says
  precisely one thing: finalisation did not complete.

**Negative**

- **It is not atomic, only recoverable.** A crash between the git branch and the
  `is_active` update leaves an orphaned table and an orphaned branch behind.
  Both are re-derived correctly on retry but if the form is simply abandoned,
  they persist as litter that nothing cleans up.
- **A genuine double-submit surfaces as an error.** Re-finalising an
  already-active form is rejected outright rather than treated as idempotent, so
  a double-click produces "Form is already finalized" rather than silently
  succeeding. This is deliberate as the two cases are genuinely different, but it is an
  inconsistency in an otherwise idempotent operation.
- **Git health is coupled to form creation.** When git integration is enabled, a
  git failure blocks finalisation entirely: no branch, no active form. This is
  intentional, since the whole point is that generated code goes through review
  but it means a repository problem stops clinical staff getting a form.
  Mitigated by the `git_integration_enabled` flag and by `isRepository()`
  short-circuiting when the directory isn't a repository at all.

**Accepted trade-off**

Recoverability was chosen over atomicity because atomicity isn't available
across these three systems at any reasonable cost. The design accepts orphaned
resources on abandonment in exchange for a guarantee that matters more: a form
visible to clinical staff is always a form that fully works.

## Evidence

- [`code/models/FormDefinition.php:112-159`](../../code/models/FormDefinition.php#L112-L159) - ordering, orphan cleanup, active flag written last
- [`code/models/FormDefinition.php:588-594`](../../code/models/FormDefinition.php#L588-L594) - existing branch treated as success on retry
- [`code/includes/GitService.php:52-56`](../../code/includes/GitService.php#L52-L56) - `GitBranchExistsException` raised rather than overwriting
- [`code/models/FormDefinition.php:558-573`](../../code/models/FormDefinition.php#L558-L573) - integration flag and non-repository short-circuit
