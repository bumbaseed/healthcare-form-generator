<?php

require_once __DIR__ . '/../includes/db_connection.php';

class FormSubmissions
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Persist or overwrite the in-progress draft for this (form_id, patient_id) pair. Only one active draft is kept per pair, so repeated saves update that single row rather than piling new ones into the dynamic table. Returns the submission_id of the draft.
     */
    public function saveDraft($formId, $patientId, $tableName, array $fieldNames, array $data, ?int $submittedBy = null): int
    {
        $this->assertValidTableName($tableName);

        $this->db->beginTransaction();
        try {
            $existing = $this->findDraftMetadata((int) $formId, (int) $patientId);

            if ($existing === null) {
                $meta = [
                    'form_id' => $formId,
                    'patient_id' => $patientId,
                    'submission_status' => 'draft',
                    'submitted_at' => date('Y-m-d H:i:s'),
                ];
                if ($submittedBy !== null) {
                    $meta['submitted_by'] = $submittedBy;
                }
                $submissionId = $this->db->insert('form_submissions', $meta, 'submission_id');
                $existingRowId = null;
            } else {
                $submissionId = (int) $existing['submission_id'];
                $existingRowId = $existing['data_table_id'] !== null ? (int) $existing['data_table_id'] : null;
                $this->db->execute(
                    "UPDATE form_submissions
                        SET submitted_at = CURRENT_TIMESTAMP,
                            submitted_by = COALESCE(:submittedBy, submitted_by)
                      WHERE submission_id = :submissionId",
                    ['submittedBy' => $submittedBy, 'submissionId' => $submissionId]
                );
            }

            $dataRowId = $this->upsertDynamicRow($tableName, $fieldNames, $data, $submissionId, $existingRowId);

            if ($existingRowId === null) {
                $this->db->execute(
                    "UPDATE form_submissions SET data_table_id = :dataRowId WHERE submission_id = :submissionId",
                    ['dataRowId' => $dataRowId, 'submissionId' => $submissionId]
                );
            }

            $this->db->commit();
            return $submissionId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Finalize a submission. If a draft already exists for this form and patient, the row is promoted to completed in place. The submission_id stays stable so any audit log entries that referenced the draft still resolve.
     *
     * $signatureName, when non-empty, is recorded as the user's typed electronic signature. signature_confirmed_at is stamped server-side with CURRENT_TIMESTAMP, so the signing time is authoritative rather than client-supplied. The caller is responsible for validating that the attestation checkbox was actually ticked before passing a name in.
     *
     * Returns the submission_id of the completed submission.
     */
    public function complete(
        $formId,
        $patientId,
        $tableName,
        array $fieldNames,
        array $data,
        ?int $submittedBy = null,
        ?string $signatureName = null
    ): int {
        $this->assertValidTableName($tableName);

        $signature = trim((string) $signatureName);
        $signatureValue = $signature !== '' ? $signature : null;

        $this->db->beginTransaction();
        try {
            $existing = $this->findDraftMetadata((int) $formId, (int) $patientId);

            if ($existing === null) {
                $now = date('Y-m-d H:i:s');
                $meta = [
                    'form_id' => $formId,
                    'patient_id' => $patientId,
                    'submission_status' => 'completed',
                    'submitted_at' => $now,
                    'completed_at' => $now,
                    'signature_name' => $signatureValue,
                    'signature_confirmed_at' => $signatureValue !== null ? $now : null,
                ];
                if ($submittedBy !== null) {
                    $meta['submitted_by'] = $submittedBy;
                }
                $submissionId = $this->db->insert('form_submissions', $meta, 'submission_id');
                $existingRowId = null;
            } else {
                $submissionId = (int) $existing['submission_id'];
                $existingRowId = $existing['data_table_id'] !== null ? (int) $existing['data_table_id'] : null;
                $this->db->execute(
                    "UPDATE form_submissions
                        SET submission_status = 'completed',
                            completed_at = CURRENT_TIMESTAMP,
                            submitted_at = CURRENT_TIMESTAMP,
                            submitted_by = COALESCE(:submittedBy, submitted_by),
                            signature_name = COALESCE(:signatureName, signature_name),
                            signature_confirmed_at = CASE
                                WHEN :signatureName2 IS NOT NULL THEN CURRENT_TIMESTAMP
                                ELSE signature_confirmed_at
                            END
                      WHERE submission_id = :submissionId",
                    [
                        'submittedBy' => $submittedBy,
                        'signatureName' => $signatureValue,
                        'signatureName2' => $signatureValue,
                        'submissionId' => $submissionId,
                    ]
                );
            }

            $dataRowId = $this->upsertDynamicRow($tableName, $fieldNames, $data, $submissionId, $existingRowId);

            if ($existingRowId === null) {
                $this->db->execute(
                    "UPDATE form_submissions SET data_table_id = :dataRowId WHERE submission_id = :submissionId",
                    ['dataRowId' => $dataRowId, 'submissionId' => $submissionId]
                );
            }

            $this->db->commit();
            return $submissionId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Fetch the current draft for a patient on a given form, if one exists. Returns an array of submission_id, data and saved_at, or null when no draft is on file. Used by generated forms to rehydrate on GET.
     */
    public function getDraftForPatient(int $formId, int $patientId, string $tableName): ?array
    {
        $this->assertValidTableName($tableName);

        $meta = $this->db->fetchOne(
            "SELECT submission_id, data_table_id, submitted_at
               FROM form_submissions
              WHERE form_id = :formId
                AND patient_id = :patientId
                AND submission_status = 'draft'
                AND is_deleted = false
              ORDER BY submitted_at DESC
              LIMIT 1",
            ['formId' => $formId, 'patientId' => $patientId]
        );

        if (!$meta) {
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT * FROM {$tableName} WHERE submission_id = :submissionId LIMIT 1",
            ['submissionId' => $meta['submission_id']]
        );

        return [
            'submission_id' => (int) $meta['submission_id'],
            'data' => $row ?: [],
            'saved_at' => $meta['submitted_at'],
        ];
    }

    /**
     * Insert a new row into the form's dynamic table, or UPDATE the existing draft row in place. Column names are re-validated here so a caller that forgets to whitelist field_names cannot slip identifiers into the SQL.
     */
    private function upsertDynamicRow(string $tableName, array $fieldNames, array $data, int $submissionId, ?int $existingRowId): int
    {
        $safeData = [];
        foreach ($fieldNames as $name) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Invalid field name: {$name}");
            }
            if (array_key_exists($name, $data)) {
                $safeData[$name] = $data[$name];
            }
        }

        if ($existingRowId === null) {
            $safeData['submission_id'] = $submissionId;
            return (int) $this->db->insert($tableName, $safeData);
        }

        if (empty($safeData)) {
            // Row already exists and there's nothing to write, just touch updated_at.
            $this->db->execute(
                "UPDATE {$tableName} SET updated_at = CURRENT_TIMESTAMP WHERE id = :rowId",
                ['rowId' => $existingRowId]
            );
            return $existingRowId;
        }

        $setParts = [];
        foreach (array_keys($safeData) as $col) {
            $setParts[] = "{$col} = :{$col}";
        }
        $sql = "UPDATE {$tableName}
                   SET " . implode(', ', $setParts) . ", updated_at = CURRENT_TIMESTAMP
                 WHERE id = :__row_id";
        $safeData['__row_id'] = $existingRowId;
        $this->db->execute($sql, $safeData);
        return $existingRowId;
    }

    private function findDraftMetadata(int $formId, int $patientId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT submission_id, data_table_id
               FROM form_submissions
              WHERE form_id = :formId
                AND patient_id = :patientId
                AND submission_status = 'draft'
                AND is_deleted = false
              ORDER BY submitted_at DESC
              LIMIT 1",
            ['formId' => $formId, 'patientId' => $patientId]
        );
        return $row ?: null;
    }

    private function assertValidTableName(string $tableName): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $tableName)) {
            throw new InvalidArgumentException('Invalid table name');
        }
    }

    public function getCompletedByPatient($patientId)
    {
        $sql = "SELECT fs.*, fd.form_name, fd.form_description,
                       su.full_name AS submitted_by_name
                FROM form_submissions fs
                JOIN form_definitions fd ON fd.form_id = fs.form_id
                LEFT JOIN staff_users su ON su.user_id = fs.submitted_by
                WHERE fs.patient_id = :patientId
                  AND fs.submission_status = 'completed'
                  AND fs.is_deleted = false
                ORDER BY fs.submitted_at DESC";

        return $this->db->fetchAll($sql, ['patientId' => $patientId]);
    }

    public function getSubmissionForPatient($submissionId, $patientId)
    {
        $sql = "SELECT fs.*, fd.form_name, fd.form_description, fd.table_name,
                       su.full_name AS submitted_by_name
                FROM form_submissions fs
                JOIN form_definitions fd ON fd.form_id = fs.form_id
                LEFT JOIN staff_users su ON su.user_id = fs.submitted_by
                WHERE fs.submission_id = :submissionId
                  AND fs.patient_id = :patientId
                  AND fs.is_deleted = false";

        return $this->db->fetchOne($sql, [
            'submissionId' => $submissionId,
            'patientId' => $patientId,
        ]);
    }

    public function getSubmissionData($tableName, $submissionId)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $tableName)) {
            throw new InvalidArgumentException('Invalid table name');
        }

        $sql = "SELECT * FROM {$tableName} WHERE submission_id = :submissionId LIMIT 1";
        return $this->db->fetchOne($sql, ['submissionId' => $submissionId]);
    }
}