<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/DepositMethodModel.php
 * Purpose: Simple model for deposit methods management
 * Security Level: PUBLIC
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use App\Models\BaseModel;

class DepositMethodModel extends BaseModel
{
    protected string $table = 'deposit_methods';
    
    protected array $fillable = [
        'gateway_id',
        'logo',
        'name',
        'type',
        'gateway_code',
        'charge',
        'charge_type',
        'minimum_deposit',
        'maximum_deposit',
        'rate',
        'currency',
        'currency_symbol',
        'field_options',
        'payment_details',
        'status'
    ];

    /**
     * Find active deposit methods
     * @return array
     */
    public function findActive(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, gateway_id, logo, name, type, gateway_code, charge, charge_type,
                        minimum_deposit, maximum_deposit, rate, currency, currency_symbol,
                        field_options, payment_details, status, created_at, updated_at
                 FROM {$this->table} 
                 WHERE status = 1 
                 ORDER BY 
                    CASE type 
                        WHEN 'auto' THEN 1 
                        WHEN 'manual' THEN 2 
                    END,
                    name ASC"
            );
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch active deposit methods: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find deposit method by ID with status check
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array 
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID must be a positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, gateway_id, logo, name, type, gateway_code, charge, charge_type,
                        minimum_deposit, maximum_deposit, rate, currency, currency_symbol,
                        field_options, payment_details, status, created_at, updated_at
                 FROM {$this->table} 
                 WHERE id = ?"
            );
            
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch deposit method {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find active deposit methods by type
     * @param string $type 'auto' or 'manual'
     * @return array
     */
    public function findByType(string $type): array 
    {
        if (!in_array($type, ['auto', 'manual'])) {
            throw new InvalidArgumentException('Type must be "auto" or "manual"');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, gateway_id, logo, name, type, gateway_code, charge, charge_type,
                        minimum_deposit, maximum_deposit, rate, currency, currency_symbol,
                        field_options, payment_details, status, created_at, updated_at
                 FROM {$this->table} 
                 WHERE status = 1 AND type = ?
                 ORDER BY name ASC"
            );
            
            $stmt->execute([$type]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch deposit methods by type {$type}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get method configuration for payment processing
     * @param int $id
     * @return array|null
     */
    public function getMethodConfig(int $id): ?array 
    {
        if ($id <= 0) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, type, gateway_code, charge, charge_type,
                        minimum_deposit, maximum_deposit, currency, payment_details
                 FROM {$this->table} 
                 WHERE id = ? AND status = 1"
            );
            
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result && !empty($result['payment_details'])) {
                // Decode JSON payment details if available
                $paymentDetails = json_decode($result['payment_details'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result['payment_config'] = $paymentDetails;
                }
            }
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch method config {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate fee for deposit method
     * @param int $methodId
     * @param float $amount
     * @return array
     */
    public function calculateMethodFee(int $methodId, float $amount): array 
    {
        $method = $this->findById($methodId);
        
        if (!$method || $method['status'] != 1) {
            return [
                'error' => 'Invalid deposit method',
                'fee' => 0,
                'net_amount' => $amount
            ];
        }

        $fee = 0.0;
        if ($method['charge_type'] === 'percentage') {
            $fee = $amount * ($method['charge'] / 100);
        } else {
            $fee = (float)$method['charge'];
        }

        return [
            'method_name' => $method['name'],
            'method_type' => $method['type'],
            'fee' => $fee,
            'fee_type' => $method['charge_type'],
            'fee_rate' => $method['charge'],
            'gross_amount' => $amount,
            'net_amount' => $amount - $fee,
            'minimum_deposit' => $method['minimum_deposit'],
            'maximum_deposit' => $method['maximum_deposit'],
            'currency' => $method['currency']
        ];
    }

    /**
     * Get supported currencies for deposit methods
     * @return array
     */
    public function getSupportedCurrencies(): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT currency, currency_symbol 
                 FROM {$this->table} 
                 WHERE status = 1 
                 ORDER BY currency ASC"
            );
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch supported currencies: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get deposit statistics for method
     * @param int $methodId
     * @return array
     */
    public function getMethodStats(int $methodId): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_deposits,
                    COUNT(CASE WHEN d.status = 'completed' THEN 1 END) as successful_deposits,
                    COUNT(CASE WHEN d.status = 'pending' THEN 1 END) as pending_deposits,
                    COUNT(CASE WHEN d.status = 'failed' THEN 1 END) as failed_deposits,
                    COALESCE(SUM(CASE WHEN d.status = 'completed' THEN d.requested_amount ELSE 0 END), 0) as total_amount,
                    COALESCE(AVG(CASE WHEN d.status = 'completed' THEN d.requested_amount END), 0) as average_amount,
                    MIN(d.created_at) as first_deposit,
                    MAX(d.created_at) as last_deposit
                 FROM deposits d
                 WHERE d.deposit_method_id = ?
                 AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            
            $stmt->execute([$methodId]);
            $result = $stmt->fetch();
            
            return $result ?: [
                'total_deposits' => 0,
                'successful_deposits' => 0,
                'pending_deposits' => 0,
                'failed_deposits' => 0,
                'total_amount' => 0.0,
                'average_amount' => 0.0,
                'first_deposit' => null,
                'last_deposit' => null
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to fetch method stats {$methodId}: " . $e->getMessage());
            return [];
        }
    }
}