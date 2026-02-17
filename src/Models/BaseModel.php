<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/BaseModel.php
 * Purpose: Abstract base model with shared DB functionality
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use App\Config\Database;

abstract class BaseModel 
{
    protected PDO $db;
    protected string $table;
    protected array $fillable = [];
    protected array $guarded = ['id', 'created_at', 'updated_at'];
    
    public function __construct() 
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Find a record by ID
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array 
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID must be a positive integer');
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch record {$id} from {$this->table}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find all records with optional conditions
     * @param array $conditions
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll(array $conditions = [], int $limit = 100, int $offset = 0): array 
    {
        try {
            $sql = "SELECT * FROM {$this->table}";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $column => $value) {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch records from {$this->table}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new record
     * @param array $data
     * @return array
     */
    public function create(array $data): array 
    {
        try {
            // Filter only fillable columns
            $filteredData = $this->filterFillableData($data);
            
            if (empty($filteredData)) {
                return [
                    'success' => false,
                    'error' => 'No valid data provided'
                ];
            }
            
            $columns = array_keys($filteredData);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            
            $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute(array_values($filteredData));
            
            if (!$success) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Failed to create record'
                ];
            }
            
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            
            return [
                'success' => true,
                'id' => $id
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to create record in {$this->table}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Update a record by ID
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update(int $id, array $data): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid ID provided'
            ];
        }
        
        try {
            // Filter only fillable columns
            $filteredData = $this->filterFillableData($data);
            
            if (empty($filteredData)) {
                return [
                    'success' => false,
                    'error' => 'No valid data provided'
                ];
            }
            
            $setClause = [];
            foreach (array_keys($filteredData) as $column) {
                $setClause[] = "{$column} = ?";
            }
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE id = ?";
            
            $params = array_values($filteredData);
            $params[] = $id;
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            
            if (!$success || $stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error' => 'Record not found or not updated'
                ];
            }
            
            return [
                'success' => true,
                'affected_rows' => $stmt->rowCount()
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to update record {$id} in {$this->table}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Delete a record by ID
     * @param int $id
     * @return array
     */
    public function delete(int $id): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid ID provided'
            ];
        }
        
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if (!$success || $stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error' => 'Record not found or not deleted'
                ];
            }
            
            return [
                'success' => true,
                'affected_rows' => $stmt->rowCount()
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to delete record {$id} from {$this->table}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Filter data to only include fillable columns
     * @param array $data
     * @return array
     */
    protected function filterFillableData(array $data): array 
    {
        if (empty($this->fillable)) {
            // If no fillable specified, exclude guarded columns
            return array_diff_key($data, array_flip($this->guarded));
        }
        
        // Only include fillable columns
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Count records with optional conditions
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int 
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table}";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $column => $value) {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int)$result['count'];
            
        } catch (PDOException $e) {
            error_log("Failed to count records in {$this->table}: " . $e->getMessage());
            return 0;
        }
    }
}