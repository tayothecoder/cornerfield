<?php
namespace App\Utils;

class ReferenceGenerator
{
    /**
     * Generate unique transaction reference ID
     */
    public static function generateTransactionId(string $type = 'TXN'): string
    {
        $date = date('Ymd');
        $time = date('His');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        
        return $type . $date . $time . $random;
    }
    
    /**
     * Generate investment reference ID
     */
    public static function generateInvestmentId(): string
    {
        return self::generateTransactionId('INV');
    }
    
    /**
     * Generate deposit reference ID
     */
    public static function generateDepositId(): string
    {
        return self::generateTransactionId('DEP');
    }
    
    /**
     * Generate withdrawal reference ID
     */
    public static function generateWithdrawalId(): string
    {
        return self::generateTransactionId('WTH');
    }
    
    /**
     * Generate manual transaction reference ID
     */
    public static function generateManualId(): string
    {
        return self::generateTransactionId('MAN');
    }
    
    /**
     * Generate profit reference ID
     */
    public static function generateProfitId(): string
    {
        return self::generateTransactionId('PRF');
    }
    
    /**
     * Generate admin reference ID
     */
    public static function generateAdminId(): string
    {
        return self::generateTransactionId('ADM');
    }
    
    /**
     * Generate referral reference ID
     */
    public static function generateReferralId(): string
    {
        return self::generateTransactionId('REF');
    }
    
    /**
     * Generate bonus reference ID
     */
    public static function generateBonusId(): string
    {
        return self::generateTransactionId('BON');
    }
}
?>