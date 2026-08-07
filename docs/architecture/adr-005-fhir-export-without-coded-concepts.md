# ADR-005: Export structurally valid FHIR without LOINC/SNOMED coding

**Status:** Accepted
**Recorded:** 2026-08-07 (retrospectively, from the implementation)

## Context

Completed submissions export as a FHIR R4 `QuestionnaireResponse`, so that data
captured here can move into other clinical systems rather than being trapped in
this application's database.

Production clinical interoperability normally goes further than structure. A
receiving system needs to know that a field means _systolic blood pressure_, not
merely that a question labelled "Systolic" was answered "140". That meaning is
carried by terminology bindings, LOINC codes for observations and measurements,
SNOMED CT for clinical findings, attached to each item in the resource.

The form builder captures no such thing. A field is a label, a database name, a
type and a required flag. There is no input where an author could attach a LOINC
code, and no vocabulary service behind the builder to look one up. Form authors
are, by design, non-technical staff who would have no basis for choosing one.

That leaves three options:

1. Attach codes inferred from field labels by matching text.
2. Attach placeholder codes to demonstrate the shape of a coded resource.
3. Emit no codes, and document the omission.

Option 1 produces confidently wrong clinical data: a label reading "Weight"
could be a patient's weight, a dosage weight, or a piece of equipment, and a
wrong LOINC code is worse than none because a receiving system will act on it.
Option 2 is the same hazard wearing a demonstration label, placeholder codes
in an exported resource are indistinguishable from real ones downstream.

## Decision

Emit a `QuestionnaireResponse` that is structurally correct and honestly
uncoded:

- correct `resourceType`, `status`, `subject`, `authored` and a well-formed
  `item[]` tree mirroring the form's fields and sections
- internal submission status mapped onto the FHIR
  `QuestionnaireResponseStatus` value set
- no `coding` on any item

Identifier systems use explicitly local namespaces:
`urn:local:patient-mrn`, `urn:local:form`, `urn:local:form-submission`, rather
than borrowing NHS or UKCore system URIs the project has no authority to issue
identifiers in.

The limitation is documented at the top of the exporter, not buried.

## Consequences

**Positive**

- **The output validates as FHIR R4** and can be ingested, rendered and stored
  by any conforming tool.
- **The gap is explicit rather than silently wrong.** A consumer can see
  immediately that items carry no terminology binding and decide what to do
  about it. Nothing in the resource overstates what the system knows.
- **Local namespaces cannot be mistaken for national ones.** The `urn:local:`
  prefix makes it unambiguous that an MRN here is not an NHS number.

**Negative**

- **The resource is not clinically interoperable in the meaningful sense.** A
  receiving system can display it and file it, but cannot reason about it,
  trigger on it, or map it into a structured record without a human first
  deciding what each item means.
- **The blocker is upstream.** Closing this gap is not an exporter change: the
  builder would first need to capture a coding per field, which needs a
  terminology service and an authoring UI aimed at someone qualified to use it.
  The exporter is the last place the problem could be fixed, not the first.

**Accepted trade-off**

The export demonstrates the structural half of interoperability correctly and
declines to fake the semantic half. For a system whose authors are explicitly
non-clinical, refusing to invent codes is the safer engineering position, and a
documented limitation is a more useful artefact than a plausible-looking
resource that would mislead a downstream consumer.

## Evidence

- [`code/models/FhirExporter.php:1-5`](../../code/models/FhirExporter.php#L1-L5) - the limitation, documented at the class header
- [`code/models/FhirExporter.php:7-10`](../../code/models/FhirExporter.php#L7-L10) - explicitly local identifier namespaces
- [`code/models/FhirExporter.php:16-48`](../../code/models/FhirExporter.php#L16-L48) - resource assembly
- [`code/models/FhirExporter.php:52`](../../code/models/FhirExporter.php#L52) - internal status mapped to the FHIR value set
