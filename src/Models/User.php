<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use Exception;

/**
 * User model for admin-side operations
 * wraps common user queries used by admin pages
 */
class User
{
    private Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Get all users with pagination
     */
    public function getAllUsers(int $page = 1, int $limit = 100): array
    {
        try {
            $offset = ($page - 1) * $limit;
            return $this->db->fetchAll(
                "SELECT id, username, email, first_name, last_name, balance, is_active, created_at
                 FROM users ORDER BY id DESC LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
        } catch (Exception $e) {
            error_log("User::getAllUsers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?array
    {
        try {
            return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        } catch (Exception $e) {
            error_log("User::findById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add amount to user balance
     */
    public function addToBalance(int $userId, float $amount): bool
    {
        try {
            $this->db->update(
                "UPDATE users SET balance = balance + ? WHERE id = ?",
                [$amount, $userId]
            );
            return true;
        } catch (Exception $e) {
            error_log("User::addToBalance error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add to total withdrawn tracker
     */
    public function addToTotalWithdrawn(int $userId, float $amount): bool
    {
        try {
            $this->db->update(
                "UPDATE users SET total_withdrawn = total_withdrawn + ? WHERE id = ?",
                [$amount, $userId]
            );
            return true;
        } catch (Exception $e) {
            error_log("User::addToTotalWithdrawn error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add to total earned tracker
     */
    public function addToTotalEarned(int $userId, float $amount): bool
    {
        try {
            $this->db->update(
                "UPDATE users SET total_earned = total_earned + ? WHERE id = ?",
                [$amount, $userId]
            );
            return true;
        } catch (Exception $e) {
            error_log("User::addToTotalEarned error: " . $e->getMessage());
            return false;
        }
    }
}
