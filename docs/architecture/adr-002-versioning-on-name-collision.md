# ADR-002: A name collision produces a new version, never an overwrite

**Status:** Accepted
**Recorded:** 2026-08-07 (retrospectively, from the implementation)

## Context

Form names are user-supplied free text, and the storage table name is derived
from that name ([ADR-001](adr-001-per-form-tables.md)). So two forms can want
the same table.

That happens in two quite different situations:

- Two admins independently create a form called "Medication Review".
- One admin revises an existing form, intending the new version to supersede
  the old one.

The second case is the dangerous one. Submissions already recorded against
"Medication Review" live in a table whose columns match that form's fields at
the time. If a revision overwrites the table, historical submissions either lose
columns their data occupies, or gain columns that were never captured for them.
The record of what a clinician actually recorded, and attested to with an
electronic signature, would change retroactively.

There is no acceptable design in which that is possible.

## Decision

Derive the table name from a slug of the form name plus a version suffix,
`form_data.medication_review_v1`. On creation, probe for an existing table with
that name and increment the version until a free name is found. Persist the
resulting number as `form_version` on the definition row.

There is no overwrite path. A name that is already taken always yields a new
version with its own table.

The version is not an internal detail, it propagates to every artefact that
identifies the form:

| Artefact                 | Form of the identifier           |
| ------------------------ | -------------------------------- |
| Storage table            | `form_data.medication_review_v2` |
| Generated PHP file       | `forms/medication_review_v2.php` |
| Git branch               | `forms/medication_review_v2`     |
| FHIR canonical reference | `urn:local:form/7\|2`            |

## Consequences

**Positive**

- **Historical submissions stay valid permanently.** v1 data remains in the v1
  table with the v1 shape. Nothing recorded can be retroactively altered by a
  later authoring action.
- **A FHIR `QuestionnaireResponse` names the exact form version it answers**, in
  the `questionnaire` element. A consumer reading an exported submission years
  later can tell precisely which question set produced it, which is the
  interoperability argument for versioning, not just the safety one.
- **No destructive path exists in the authoring flow.** Creating a form cannot
  damage existing data, so the operation needs no confirmation step and no
  undo.

**Negative**

- **The version number tracks name collision, not semantic revision.** Two
  genuinely unrelated forms that happen to share a name become v1 and v2 of each
  other, implying a lineage that doesn't exist. Conversely, renaming a form on
  revision produces a fresh v1 rather than v2 of the original. The mechanism
  cannot distinguish "revision of" from "coincidentally named like".
- **Version discovery is a read-then-write.** The next free version is found by
  probing `information_schema` and then inserting, so there is a race window
  between probe and insert. Two admins finalising same-named forms in the same
  instant could both resolve to v2. The risk is low as authoring is admin-only,
  deliberate and infrequent, but the operation is not atomic, and a sequence or
  a unique constraint on `(slug, version)` would close it.
- **Slug collapse creates false collisions.** "Medication Review",
  "medication-review" and "Medication Review" all slugify identically and so
  become versions of one another.

**Accepted trade-off**

The mechanism is deliberately blunt: it guarantees the property that matters
(recorded data is immutable) at the cost of a version number that sometimes
means less than it appears to. Making version lineage semantically accurate
would need an explicit "revise this form" flow in the builder, which was out of
scope.

## Evidence

- [`code/models/FormDefinition.php:24-63`](../../code/models/FormDefinition.php#L24-L63) — version probing and persistence
- [`code/models/FormDefinition.php:164-171`](../../code/models/FormDefinition.php#L164-L171) — versioned filename for the generated file
- [`code/models/FormDefinition.php:576-581`](../../code/models/FormDefinition.php#L576-L581) — versioned git branch name
- [`code/models/FhirExporter.php:31-33`](../../code/models/FhirExporter.php#L31-L33) — version in the FHIR canonical reference
