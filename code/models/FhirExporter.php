<?php
/**
 * Builds a FHIR R4 QuestionnaireResponse document from an internal form submission. The output has the right resourceType, required fields, and a well-formed item / answer tree, so it can be consumed by any FHIR tool. Coded concepts such as LOINC and SNOMED are not attached because the form builder doesn't capture them.
 *
 */
class FhirExporter
{
    /** Local identifier namespaces used in lieu of real NHS/UKCore systems. */
    private const PATIENT_IDENTIFIER_SYSTEM = 'urn:local:patient-mrn';
    private const QUESTIONNAIRE_URL_BASE = 'urn:local:form';

    /**
     * Build a QuestionnaireResponse resource as a PHP array. $submission is a row from form_submissions, $form is a form definition with fields populated (as returned by FormDefinition::getFormWithFields), $patient is a row holding patient_id, patient_identifier, first_name and last_name, and $data is the matching row from the dynamic form-data table keyed by field_name (null or empty means no answers recorded).
     */
    public function buildQuestionnaireResponse(array $submission, array $form, array $patient, ?array $data): array
    {
        $status = $this->mapStatus($submission['submission_status'] ?? 'completed');
        $authored = $this->isoTimestamp(
            $submission['completed_at']
            ?? $submission['submitted_at']
            ?? 'now'
        );

        $resource = [
            'resourceType' => 'QuestionnaireResponse',
            'id' => 'submission-' . (int) $submission['submission_id'],
            'identifier' => [
                'system' => 'urn:local:form-submission',
                'value' => (string) $submission['submission_id'],
            ],
            'questionnaire' => self::QUESTIONNAIRE_URL_BASE . '/'
                . (int) $form['form_id']
                . '|' . (int) ($form['form_version'] ?? 1),
            'status' => $status,
            'subject' => $this->buildPatientReference($patient),
            'authored' => $authored,
            'item' => $this->buildItems($form['fields'] ?? [], $data ?? []),
        ];

        if (!empty($submission['submitted_by_name'])) {
            $resource['author'] = [
                'display' => $submission['submitted_by_name'],
            ];
        }

        return $resource;
    }

    /**
     * Map the internal submission_status to a FHIR QuestionnaireResponseStatus code. FHIR's enum is: in-progress | completed | amended | entered-in-error | stopped.
     */
    private function mapStatus(string $internalStatus): string
    {
        return match ($internalStatus) {
            'draft' => 'in-progress',
            'completed' => 'completed',
            default => 'completed',
        };
    }

    private function buildPatientReference(array $patient): array
    {
        $displayParts = array_filter([
            $patient['first_name'] ?? null,
            $patient['last_name'] ?? null,
        ]);

        return [
            'reference' => 'Patient/' . (int) $patient['patient_id'],
            'identifier' => [
                'system' => self::PATIENT_IDENTIFIER_SYSTEM,
                'value' => (string) ($patient['patient_identifier'] ?? $patient['patient_id']),
            ],
            'display' => $displayParts ? implode(' ', $displayParts) : 'Patient',
        ];
    }

    /**
     * Build the item[] tree. Fields with a _section key in field_options are nested under a group item for that section, so the response mirrors the visual grouping on the form.
     */
    private function buildItems(array $fields, array $data): array
    {
        $groups = []; // section label => [fields]
        $ungrouped = [];
        $sectionOrder = []; // preserve first-seen order for groups

        foreach ($fields as $field) {
            $section = $this->extractSection($field);
            if ($section === null) {
                $ungrouped[] = $field;
                continue;
            }
            if (!isset($groups[$section])) {
                $groups[$section] = [];
                $sectionOrder[] = $section;
            }
            $groups[$section][] = $field;
        }

        $items = [];
        foreach ($ungrouped as $field) {
            $items[] = $this->buildFieldItem($field, $data);
        }
        foreach ($sectionOrder as $section) {
            $items[] = [
                'linkId' => 'section-' . $this->slugify($section),
                'text' => $section,
                'item' => array_map(
                    fn($f) => $this->buildFieldItem($f, $data),
                    $groups[$section]
                ),
            ];
        }

        return $items;
    }

    private function extractSection(array $field): ?string
    {
        if (empty($field['field_options'])) {
            return null;
        }
        $opts = json_decode((string) $field['field_options'], true);
        if (!is_array($opts)) {
            return null;
        }
        $section = $opts['_section'] ?? null;
        return (is_string($section) && $section !== '') ? $section : null;
    }

    /**
     * One item per form field. Empty answers are omitted entirely (FHIR treats a missing answer[] as "unanswered", which is more faithful than emitting valueString="").
     */
    private function buildFieldItem(array $field, array $data): array
    {
        $name = $field['field_name'];
        $item = [
            'linkId' => $name,
            'text' => $field['field_label'] ?? $name,
        ];

        $raw = $data[$name] ?? null;
        $answers = $this->buildAnswers($field, $raw);
        if (!empty($answers)) {
            $item['answer'] = $answers;
        }
        return $item;
    }

    /**
     * Field-type and data-type driven mapping to FHIR value[x] answer elements. Each returned element is a single answer object
     */
    private function buildAnswers(array $field, $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $fieldType = strtolower((string) ($field['field_type'] ?? 'text'));
        $dataType = strtoupper((string) ($field['data_type'] ?? 'VARCHAR'));

        // Multi-select (checkbox storing a JSON array) expands to repeated answers.
        if ($fieldType === 'checkbox' && is_string($raw) && str_starts_with(trim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_map(
                    fn($v) => ['valueString' => (string) $v],
                    array_filter($decoded, fn($v) => $v !== null && $v !== '')
                ));
            }
        }

        return [$this->buildSingleAnswer($fieldType, $dataType, $raw)];
    }

    private function buildSingleAnswer(string $fieldType, string $dataType, $raw): array
    {
        switch ($fieldType) {
            case 'boolean':
                return ['valueBoolean' => $this->toBool($raw)];

            case 'date':
                return ['valueDate' => $this->formatDate($raw)];

            case 'datetime':
            case 'datetime-local':
                return ['valueDateTime' => $this->isoTimestamp($raw)];

            case 'number':
                return $dataType === 'INTEGER'
                    ? ['valueInteger' => (int) $raw]
                    : ['valueDecimal' => (float) $raw];

            case 'checkbox':
                // Single checkbox stored as 0, 1, true or false; coerce to boolean.
                return ['valueBoolean' => $this->toBool($raw)];

            default:
                // text, textarea, email, phone, select, radio, and unknown types
                return ['valueString' => (string) $raw];
        }
    }

    private function toBool($raw): bool
    {
        if (is_bool($raw))
            return $raw;
        return in_array($raw, [1, '1', 't', 'true', 'TRUE', 'yes', 'on'], true);
    }

    private function formatDate($raw): string
    {
        $ts = is_numeric($raw) ? (int) $raw : strtotime((string) $raw);
        return $ts ? date('Y-m-d', $ts) : (string) $raw;
    }

    private function isoTimestamp($raw): string
    {
        $ts = is_numeric($raw) ? (int) $raw : strtotime((string) $raw);
        return $ts ? date('c', $ts) : date('c');
    }

    private function slugify(string $s): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s)));
        return trim((string) $slug, '-') ?: 'section';
    }
}
