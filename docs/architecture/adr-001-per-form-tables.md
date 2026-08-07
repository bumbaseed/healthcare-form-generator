# ADR-001: Generate a physical table per form instead of using EAV

**Status:** Accepted
**Recorded:** 2026-08-07 (retrospectively, from the implementation)

## Context

The form builder lets non-developers define arbitrary forms at runtime. Any
number of fields, each with a label, a name, a type and a required flag. The
answers to those forms have to be stored somewhere, and the storage shape isn't
known until an admin presses Save.

Three standard options:

1. **Entity–attribute–value.** One generic answers table keyed by
   `(submission_id, field_name, value)`, with `value` as text.
2. **Document column.** One `JSONB` column per submission holding the whole
   answer set.
3. **Physical table per form.** Derive real columns with real types from the
   field definitions and issue `CREATE TABLE` when the form is finalised.

The deciding consideration was what happens to the data after it is captured.
Clinical form data is read by people who are not the application: analysts
running reports, and in a real deployment an integration engine. Under EAV, a
date is a string in a text column and every query needs a pivot before it means
anything. That cost is paid forever by everyone downstream, in exchange for
convenience at write time in one part of one application.

## Decision

Generate a physical table per finalised form, in a dedicated `form_data` schema,
with columns derived from the field definitions and mapped to real PostgreSQL
types (`VARCHAR(n)`, `TEXT`, `INTEGER`, `NUMERIC`, `DATE`, `TIMESTAMP`,
`BOOLEAN`).

Each generated table also gets:

- a `submission_id` foreign key to `public.form_submissions` with
  `ON DELETE CASCADE`
- `NOT NULL` on columns whose field is marked required
- an index on `submission_id`, the only column the application joins on
- the shared `update_updated_at_column()` trigger

## Consequences

**Positive**

- Submission data is queryable with ordinary SQL. A date column is a `DATE`, so
  range queries, sorting and aggregation work without a decoding layer.
- Integrity is enforced by the database rather than by application convention.
  A required field is `NOT NULL`; deleting a submission cascades to its answers.
- Type errors surface at write time, where they can be attributed to a specific
  field, instead of at read time in a report.

**Negative**

- **The application issues DDL at runtime.** It needs `CREATE TABLE` rights on
  the `form_data` schema — a materially larger privilege than a typical
  application role holds, and the main security cost of this decision.
- **Identifiers cannot be parameterised.** SQL placeholders bind values, not
  table or column names, so generated identifiers are interpolated into the
  statement. This is mitigated, not eliminated: field and table names are
  whitelist-validated against `^[a-zA-Z_][a-zA-Z0-9_]*$` before use, the form
  builder applies the same pattern at input time, and `dropTable()` refuses to
  operate outside the `form_data` schema. Any future code path that reaches
  these methods must preserve that validation.
- **Schema proliferation.** One table per form _version_. Twenty forms revised twice each is sixty tables.
- **Changing a finalised form is a migration, not an update.** There is no
  cheap `ALTER` path that keeps existing submissions valid, which is precisely
  why ADR-002 versions rather than mutates.

**Accepted trade-off**

Runtime DDL is unusual in application code and would be wrong in a
multi-tenant SaaS product. It is defensible here because form creation is
admin-only, low-frequency, and audited — and because the alternative pushes a
permanent decoding cost onto every downstream consumer of clinical data.

## Evidence

- [`code/models/DynamicTable.php:21-63`](../../code/models/DynamicTable.php#L21-L63) — table creation, index, trigger
- [`code/models/DynamicTable.php:68-98`](../../code/models/DynamicTable.php#L68-L98) — column definition from a field row
- [`code/models/DynamicTable.php:103-132`](../../code/models/DynamicTable.php#L103-L132) — type mapping and identifier whitelisting
- [`code/models/DynamicTable.php:172-198`](../../code/models/DynamicTable.php#L172-L198) — schema-restricted drop
- [`form-builder.php:50-52`](../../form-builder.php#L50-L52) — the same identifier pattern enforced at input
