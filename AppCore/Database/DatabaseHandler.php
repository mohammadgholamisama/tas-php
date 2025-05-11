<?php
// MyTelegramBot/AppCore/Database/DatabaseHandler.php

namespace MyTelegramBot\AppCore\Database;

use PDO;
use PDOException;
use MyTelegramBot\AppCore\Helpers\Logger; // برای لاگ کردن خطاهای دیتابیس

class DatabaseHandler
{
    private static ?PDO $pdoInstance = null; // Singleton instance
    private array $config;
    private ?Logger $logger; // برای لاگ کردن خطاهای اتصال

    public function __construct(array $dbConfig, Logger $logger = null)
    {
        $this->config = $dbConfig;
        $this->logger = $logger;
    }

    public function getConnection(): ?PDO
    {
        if (self::$pdoInstance === null) {
            $dsn = "mysql:host={$this->config['db_host']};dbname={$this->config['db_name']};charset={$this->config['db_charset']}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // پرتاب استثنا در صورت خطا
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // بازیابی نتایج به صورت آرایه انجمنی
                PDO::ATTR_EMULATE_PREPARES   => false,                // استفاده از prepared statement های واقعی
            ];

            try {
                self::$pdoInstance = new PDO($dsn, $this->config['db_user'], $this->config['db_pass'], $options);
                if ($this->logger) {
                    // می‌توانید لاگ اتصال موفق را به صورت debug یا info ثبت کنید
                    // $this->logger->debug("Database connection established successfully to {$this->config['db_name']}.");
                }
            } catch (PDOException $e) {
                $errorMessage = "Database Connection Error: " . $e->getMessage() . " (DSN: {$dsn})";
                if ($this->logger) {
                    // استفاده از لاگر برای ثبت خطای اتصال به دیتابیس
                    // اگر فایل لاگ جداگانه‌ای برای دیتابیس در کانفیگ دارید، می‌توانید از آن استفاده کنید
                    // یا مستقیماً با لاگر اصلی لاگ کنید
                    $this->logger->critical($errorMessage, $e);
                } else {
                    // اگر لاگر در دسترس نیست، از error_log استفاده کنید
                    error_log($errorMessage);
                }
                // برای جلوگیری از ادامه کار برنامه بدون دیتابیس، null برمی‌گردانیم یا می‌توانید استثنا را مجدد پرتاب کنید
                // throw $e; // یا
                return null;
            }
        }
        return self::$pdoInstance;
    }

    // متدهای کمکی دیگر برای کار با دیتابیس (query, execute, fetch, etc.) می‌توانند در اینجا یا کلاس‌های دیگر اضافه شوند
    // برای مثال:
    // public function query(string $sql, array $params = []): \PDOStatement|false
    // {
    //     $pdo = $this->getConnection();
    //     if ($pdo) {
    //         $stmt = $pdo->prepare($sql);
    //         $stmt->execute($params);
    //         return $stmt;
    //     }
    //     return false;
    // }
}