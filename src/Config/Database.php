<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Config/Database.php
 * Purpose: Database connection class - supports both static singleton PDO access
 *          and instantiated wrapper with helper methods (fetchOne, fetchAll, etc.)
 * Security Level: SYSTEM_ONLY
 *
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Config;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?PDO $instance = null;
    private PDO $pdo;

    /**
     * Constructor - wraps the singleton PDO connection
     * Accepts optional config array for backward compat but ignores it
     * since we always use the singleton connection
     */
    public function __construct(mixed $config = null)
    {
        $this->pdo = self::getInstance();
    }

    /**
     * Get the single database connection instance (static access)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                EnvLoader::load();
                $host = EnvLoader::get('DB_HOST', 'localhost');
                $dbname = EnvLoader::get('DB_NAME', 'cornerfield_db');
                $username = EnvLoader::get('DB_USER', 'cornerfield');
                $password = EnvLoader::get('DB_PASS', '');
                $port = EnvLoader::get('DB_PORT', '3306');

                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4;port={$port}";

                self::$instance = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]);

            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new PDOException("Database connection unavailable");
            }
        }

        return self::$instance;
    }

    /**
     * Close the connection (for testing)
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
    }

    // -- helper methods used by admin-side models --

    /**
     * Fetch a single row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return the statement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Execute raw SQL (alias for query)
     */
    public function raw(string $sql, array $params = []): PDOStatement
    {
        return $this->query($sql, $params);
    }

    /**
     * Insert - supports both raw SQL and table-based convenience:
     *   insert("INSERT INTO ...", [params])
     *   insert("table_name", ["col" => "val", ...])
     */
    public function insert(string $sqlOrTable, array $params = []): string
    {
        // detect if first arg is a table name (no spaces) vs raw SQL
        if (strpos($sqlOrTable, ' ') === false && !empty($params)) {
            $columns = array_keys($params);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO {$sqlOrTable} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($params));
        } else {
            $stmt = $this->pdo->prepare($sqlOrTable);
            $stmt->execute($params);
        }
        return $this->pdo->lastInsertId();
    }

    /**
     * Update - supports both raw SQL and table-based convenience:
     *   update("UPDATE ...", [params])
     *   update("table_name", ["col" => "val"], "id = ?", [id])
     */
    public function update(string $sqlOrTable, array $dataOrParams = [], string $where = '', array $whereParams = []): int
    {
        if (strpos($sqlOrTable, ' ') === false && !empty($dataOrParams) && $where !== '') {
            $setClauses = [];
            $values = [];
            foreach ($dataOrParams as $col => $val) {
                $setClauses[] = "{$col} = ?";
                $values[] = $val;
            }
            $sql = "UPDATE {$sqlOrTable} SET " . implode(', ', $setClauses) . " WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($values, $whereParams));
        } else {
            $stmt = $this->pdo->prepare($sqlOrTable);
            $stmt->execute($dataOrParams);
        }
        return $stmt->rowCount();
    }

    /**
     * Delete - supports both raw SQL and table-based convenience:
     *   delete("DELETE FROM ...", [params])
     *   delete("table_name", "id = ?", [id])
     */
    public function delete(string $sqlOrTable, mixed $paramsOrWhere = [], array $whereParams = []): int
    {
        if (strpos($sqlOrTable, ' ') === false) {
            $where = is_string($paramsOrWhere) ? $paramsOrWhere : '';
            $params = is_string($paramsOrWhere) ? $whereParams : $paramsOrWhere;
            $sql = "DELETE FROM {$sqlOrTable}" . ($where ? " WHERE {$where}" : '');
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $this->pdo->prepare($sqlOrTable);
            $params = is_array($paramsOrWhere) ? $paramsOrWhere : [];
            $stmt->execute($params);
        }
        return $stmt->rowCount();
    }

    /**
     * Prepare a statement (PDO passthrough)
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    // note: PHP method names are case-insensitive, so rollBack() already covers rollback()

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Check if database connection is active
     */
    public function isConnected(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get the underlying PDO instance
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
