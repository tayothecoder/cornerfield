<?php
namespace App\Config;

use PDO;
use PDOException;
use PDOStatement;
use Exception;

// src/Config/Database.php - Modern Approach with Dependency Injection

class Database
{
    private $connection;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;

    public function __construct(array $config = [])
    {
        $this->host = $config['host'] ?? Config::getDbHost();
        $this->dbname = $config['dbname'] ?? Config::getDbName();
        $this->username = $config['username'] ?? Config::getDbUser();
        $this->password = $config['password'] ?? Config::getDbPassword();
        $this->charset = $config['charset'] ?? 'utf8mb4';
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    private function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                PDO::ATTR_PERSISTENT => false, // Use connection pooling in production
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $e) {
            if (Config::isDebug()) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            } else {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    // Modern query builder methods
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);

        return (int) $this->getConnection()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        try {
            $sets = [];
            $allParams = [];

            // Build SET clause with positional parameters
            foreach ($data as $column => $value) {
                if (is_object($value) && method_exists($value, '__toString')) {
                    // Handle raw expressions
                    $sets[] = "{$column} = " . $value->__toString();
                } else {
                    $sets[] = "{$column} = ?";
                    $allParams[] = $value;
                }
            }
            $setClause = implode(', ', $sets);

            // Add where parameters
            foreach ($whereParams as $param) {
                $allParams[] = $param;
            }

            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = $this->query($sql, $allParams);
            return $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Database update error: " . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->getConnection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getConnection()->commit();
    }

    public function rollback(): void
    {
        $this->getConnection()->rollBack();
    }

    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    // Raw SQL expression method
    public function raw($expression) {
        return new class($expression) {
            public $expression;
            
            public function __construct($expression) {
                $this->expression = $expression;
            }
            
            public function __toString() {
                return $this->expression;
            }
        };
    }
}

// Modern Database Factory (Industry Standard)
class DatabaseFactory
{
    private static $instances = [];

    public static function create(string $name = 'default', array $config = []): Database
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new Database($config);
        }
        return self::$instances[$name];
    }

    public static function destroy(string $name = 'default'): void
    {
        if (isset(self::$instances[$name])) {
            self::$instances[$name]->disconnect();
            unset(self::$instances[$name]);
        }
    }
}