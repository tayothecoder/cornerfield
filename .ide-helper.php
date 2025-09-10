<?php
/**
 * IDE Helper File for Cornerfield
 * This file helps IDEs and linters understand our custom classes
 * 
 * @see https://github.com/barryvdh/laravel-ide-helper
 */

// Database Classes (Legacy compatibility)
/**
 * @property-read string $host
 * @property-read string $dbname
 * @property-read string $username
 * @property-read string $password
 * @property-read int $port
 * @method mixed fetchAll(string $query, array $params = [])
 * @method mixed fetchOne(string $query, array $params = [])
 * @method bool insert(string $table, array $data)
 * @method bool update(string $table, array $data, string $where, array $params = [])
 * @method bool delete(string $table, string $where, array $params = [])
 * @method mixed raw(string $query, array $params = [])
 * @method void beginTransaction()
 * @method void commit()
 * @method void rollback()
 */
class Database {}

// Model Classes
/**
 * @method array getAllUsers(int $page = 1, int $limit = 20, string $search = '', string $status = 'all')
 * @method int getTotalUsersCount(string $search = '', string $status = 'all')
 * @method array getUserById(int $userId)
 * @method bool updateUser(int $userId, array $data)
 * @method bool updateUserBalance(int $userId, float $amount, string $type = 'add', int $adminId = null, string $description = '')
 * @method bool toggleUserStatus(int $userId)
 * @method array getUserStatistics()
 * @method bool startImpersonation(int $userId, int $adminId)
 * @method bool stopImpersonation()
 * @method bool isImpersonating()
 */
class UserManagement {}

/**
 * @method string getSetting(string $key, string $default = '')
 * @method bool updateSetting(string $key, string $value)
 * @method array getAllSettings()
 */
class AdminSettings {}

/**
 * @method array getCurrentAdmin()
 * @method bool isLoggedIn()
 * @method bool authenticate(string $email, string $password)
 */
class AdminController {}

// Service Classes
/**
 * @method array getGatewayConfig()
 * @method array getSupportedCryptocurrencies()
 * @method bool updateGatewaySettings(array $settings)
 * @method array testPaymentGateway(string $gateway)
 * @method array createCryptomusPayment(float $amount, string $currency, string $orderId, string $description, string $userEmail)
 * @method array createNOWPaymentsPayment(float $amount, string $currency, string $orderId, string $description, string $userEmail)
 * @method bool verifyCallback(string $gateway, array $data, string $signature)
 */
class PaymentGatewayService {}

/**
 * @method array getEmailConfig()
 * @method array getEmailTemplates()
 * @method bool updateEmailSettings(array $settings)
 * @method array testSMTPConnection()
 * @method bool sendEmail(string $to, string $subject, string $htmlBody, string $textBody = null)
 * @method bool logEmail(string $to, string $subject, string $status, string $details = '')
 */
class EmailService {}

/**
 * @method array getAllSettings()
 * @method array getPlatformSettings()
 * @method array getPaymentGatewaySettings()
 * @method array getEmailSettings()
 * @method array getSupportSettings()
 * @method array getTransferSettings()
 * @method array getSecuritySettings()
 * @method array getNotificationSettings()
 * @method bool updateSettingsByCategory(string $category, array $settings)
 * @method array getSystemHealth()
 * @method array getSystemStats()
 */
class EnhancedAdminSettings {}

/**
 * @method array getTickets(array $filters = [])
 * @method array getTicket(int $id)
 * @method bool createTicket(array $data)
 * @method bool addReply(int $ticketId, array $data)
 * @method bool updateTicketStatus(int $ticketId, string $status)
 * @method array getTicketStats()
 * @method array getCategories()
 * @method array getPriorities()
 */
class SupportService {}

/**
 * @method array getTransferConfig()
 * @method array processTransfer(array $data)
 * @method array validateTransfer(array $data)
 * @method array getUserDailyTransfers(int $userId)
 * @method array getTransferHistory(array $filters = [])
 * @method array getTransferStats()
 * @method bool cancelTransfer(int $transferId)
 */
class UserTransferService {}

// Utility Classes
/**
 * @method static void start()
 * @method static void regenerate()
 * @method static void destroy()
 */
class SessionManager {}

/**
 * @method static array checkSystemHealth()
 * @method static array getDatabaseStatus()
 * @method static array getServerStatus()
 */
class SystemHealth {}

// Configuration Classes
/**
 * @method static string getSiteName()
 * @method static string getSiteDescription()
 * @method static string getCurrencySymbol()
 * @method static bool isDebug()
 * @method static int getSessionLifetime()
 * @method static string getEncryptionKey()
 */
class Config {}

// CSRF Protection
/**
 * @method static string getTokenField()
 * @method static void validateRequest()
 * @method static string generateToken()
 */
class CSRFProtection {}
