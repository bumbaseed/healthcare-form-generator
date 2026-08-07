<?php
/**
 * Singleton PDO wrapper around the PostgreSQL connection. Connection settings come from config/database.php with optional overrides from config/database.local.php.
 */

class Database
{
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->connect();
    }

    private function connect()
    {
        try {
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['database']
            );

            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );

        } catch (PDOException $e) {
            $this->handleError("Database connection failed", $e);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Run a parameterised query and return the PDOStatement.
     */
    public function query($sql, $params = [])
    {
        try {
            // Postgres rejects PHP false as an empty string when binding to boolean columns. Coerce booleans to 0/1, which Postgres accepts for every boolean context.
            $params = array_map(fn($v) => is_bool($v) ? (int) $v : $v, $params);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->handleError("Query execution failed", $e);
        }
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * INSERT a row built from $data and return the new primary key. The SQL is assembled from $data's keys, so identifiers are validated against a strict regex (table can be schema-qualified, returning column cannot) to keep user input out of the SQL string.
     */
    public function insert($table, $data, $returning = 'id')
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $returning)) {
            throw new Exception("Invalid column name: $returning");
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $returning
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->handleError("Insert failed on $table", $e);
        }
    }

    /**
     * Run an UPDATE or DELETE and return the affected row count.
     */
    public function execute($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollback()
    {
        return $this->pdo->rollBack();
    }

    /**
     * Log details and rethrow as a user-facing Exception. In debug mode the original PDO message is surfaced; in production a generic message is shown so PDO internals don't leak to end users.
     */
    private function handleError($message, PDOException $e): never
    {
        error_log($message . ": " . $e->getMessage());

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        if ($appConfig['debug_mode']) {
            throw new Exception($message . ": " . $e->getMessage());
        } else {
            throw new Exception("A database error occurred. Please try again later.");
        }
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
