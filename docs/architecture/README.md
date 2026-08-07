# Architecture Decision Records

Records of the significant design decisions taken while building the Healthcare
Form Generator, in the standard _context / decision / consequences_ format.

These were recorded retrospectively from the implementation rather than written
before it. The reasoning is contemporaneous as each decision was made and
documented in code comments during development, but the ADR files themselves
were extracted afterwards. Every record cites the code that implements it.

Consequences sections include the negative ones. A decision recorded without its
costs is a justification, not a record.

| ADR                                                      | Decision                                                           | Why it mattered                                                           |
| -------------------------------------------------------- | ------------------------------------------------------------------ | ------------------------------------------------------------------------- |
| [ADR-001](adr-001-per-form-tables.md)                    | Generate a physical table per form instead of using EAV            | Keeps submission data queryable with real types and real constraints      |
| [ADR-002](adr-002-versioning-on-name-collision.md)       | A name collision produces a new version, never an overwrite        | Submissions already recorded against a form can never be invalidated      |
| [ADR-003](adr-003-git-plumbing-for-form-branches.md)     | Commit generated forms with git plumbing against a temporary index | The developer's working tree is never touched by a web request            |
| [ADR-004](adr-004-lockout-timing-in-sql.md)              | Compute account-lockout state in SQL, not PHP                      | Application and database clocks cannot drift apart                        |
| [ADR-005](adr-005-fhir-export-without-coded-concepts.md) | Export structurally valid FHIR without LOINC/SNOMED coding         | Honest structural output beats invented codes that are semantically wrong |
| [ADR-006](adr-006-finalisation-ordering.md)              | Write the active flag last and make every prior step idempotent    | A form is either fully finalised or safely re-runnable, never half-live   |
