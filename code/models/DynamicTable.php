<?php
/**
 * Creates and modifies the per-form tables that hold submission data. Every form built through the form builder gets one of these tables under the form_data schema, with columns derived from the field definitions.
 */

require_once dirname(__DIR__) . '/includes/db_connection.php';

class DynamicTable
{
    private $db;
    private const SCHEMA = 'form_data';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new dynamic table for a form. The qualified name comes from form_definitions.table_name; if it is missing, fall back to the legacy form_data.form_data_{id} pattern so older rows still work.
     */
    public function createFormTable($formId, $fields, ?string $qualifiedName = null)
    {
        $tableName = $qualifiedName ?? (self::SCHEMA . ".form_data_{$formId}");

        $parts = explode('.', $tableName);
        $rawTable = count($parts) === 2 ? $parts[1] : $parts[0];
        $schema = count($parts) === 2 ? $parts[0] : self::SCHEMA;

        if (!$this->validateTableName($rawTable)) {
            throw new Exception("Invalid table name: $rawTable");
        }

        if ($this->tableExists($rawTable, $schema)) {
            throw new Exception("Table $tableName already exists");
        }

        $columns = [
            'id SERIAL PRIMARY KEY',
            'submission_id INTEGER NOT NULL REFERENCES public.form_submissions(submission_id) ON DELETE CASCADE',
        ];
        foreach ($fields as $field) {
            $columns[] = $this->buildColumnDefinition($field);
        }
        $columns[] = 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        $columns[] = 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $sql = "CREATE TABLE {$schema}.{$rawTable} (" . implode(', ', $columns) . ');';

        try {
            $this->db->execute($sql);

            // Index on submission_id, the only column the app joins on.
            $indexSql = "CREATE INDEX idx_{$rawTable}_submission ON {$schema}.{$rawTable}(submission_id);";
            $this->db->execute($indexSql);

            $this->createUpdateTrigger($rawTable, $schema);

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to create table $tableName: " . $e->getMessage());
        }
    }

    /**
     * Build "<column> <type> [NOT NULL] [DEFAULT ...]" from a field row. Field name is whitelist-validated to keep user-supplied identifiers out of the SQL string.
     */
    private function buildColumnDefinition($field)
    {
        $fieldName = $field['field_name'];
        $dataType = $this->mapDataTypeToSQL($field['data_type'], $field['max_length'] ?? null);
        $isRequired = $field['is_required'] ?? false;
        $defaultValue = $field['default_value'] ?? null;

        if (!$this->validateFieldName($fieldName)) {
            throw new Exception("Invalid field name: $fieldName");
        }

        $sql = "$fieldName $dataType";

        if ($isRequired) {
            $sql .= " NOT NULL";
        }

        if ($defaultValue !== null && $defaultValue !== '') {
            // Numbers and booleans go in unquoted; everything else needs quoting.
            if (
                in_array($dataType, ['INTEGER', 'NUMERIC', 'BOOLEAN']) ||
                in_array($field['data_type'], ['INTEGER', 'NUMERIC', 'BOOLEAN'])
            ) {
                $sql .= " DEFAULT " . $defaultValue;
            } else {
                $sql .= " DEFAULT '" . addslashes($defaultValue) . "'";
            }
        }

        return $sql;
    }

    /**
     * Map the application's logical data types onto PostgreSQL types. Unknown types fall back to VARCHAR(255).
     */
    private function mapDataTypeToSQL($dataType, $maxLength = null)
    {
        $mapping = [
            'VARCHAR' => 'VARCHAR(' . ($maxLength ?? 255) . ')',
            'TEXT' => 'TEXT',
            'INTEGER' => 'INTEGER',
            'NUMERIC' => 'NUMERIC',
            'DATE' => 'DATE',
            'TIMESTAMP' => 'TIMESTAMP',
            'BOOLEAN' => 'BOOLEAN'
        ];

        $dataType = strtoupper(trim($dataType));

        if (!isset($mapping[$dataType])) {
            return 'VARCHAR(255)';
        }

        return $mapping[$dataType];
    }

    private function validateTableName($tableName)
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName) === 1;
    }

    private function validateFieldName($fieldName)
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldName) === 1;
    }

    public function tableExists($tableName, string $schema = self::SCHEMA)
    {
        $sql = "SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = :schema
            AND table_name = :tableName
        )";

        try {
            $result = $this->db->fetchOne($sql, ['schema' => $schema, 'tableName' => $tableName]);
            return $result['exists'] === true || $result['exists'] === 't';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Attach the shared updated_at trigger function (defined in schema.sql) to a freshly created dynamic table. Failures are logged but not thrown; the trigger is convenience, not correctness.
     */
    private function createUpdateTrigger($tableName, string $schema = self::SCHEMA)
    {
        $sql = "CREATE TRIGGER update_{$tableName}_updated_at
                BEFORE UPDATE ON {$schema}.{$tableName}
                FOR EACH ROW
                EXECUTE FUNCTION update_updated_at_column();";

        try {
            $this->db->execute($sql);
            return true;
        } catch (Exception $e) {
            error_log("Failed to create update trigger for $tableName: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drop a form table. Restricted to the form_data schema as a guard against accidentally dropping a public table.
     */
    public function dropTable($tableName)
    {
        $parts = explode('.', $tableName);
        $rawTable = count($parts) === 2 ? $parts[1] : $parts[0];
        $schema = count($parts) === 2 ? $parts[0] : self::SCHEMA;

        if (!$this->validateTableName($rawTable)) {
            throw new Exception("Invalid table name: $rawTable");
        }

        if ($schema !== self::SCHEMA) {
            throw new Exception("Can only drop tables in the " . self::SCHEMA . " schema");
        }

        if (!$this->tableExists($rawTable, $schema)) {
            throw new Exception("Table {$schema}.{$rawTable} does not exist");
        }

        $sql = "DROP TABLE IF EXISTS {$schema}.{$rawTable} CASCADE;";

        try {
            $this->db->execute($sql);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to drop table $tableName: " . $e->getMessage());
        }
    }

}
