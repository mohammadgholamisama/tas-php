<?php
// MyTelegramBot/AppCore/Handlers/BaseHandler.php

namespace MyTelegramBot\AppCore\Handlers;

use MyTelegramBot\AppCore\Helpers\Logger;
use MyTelegramBot\AppCore\Services\TelegramApiService; // سرویس API تلگرام
use PDO;

abstract class BaseHandler
{
    protected int $chatId;
    protected ?int $userId = null; // شناسه کاربر فرستنده
    protected ?array $user;       // اطلاعات کامل کاربر (از $update['message']['from'] یا $update['callback_query']['from'])
    protected ?PDO $pdo;
    protected Logger $logger;
    protected array $config;
    protected TelegramApiService $telegramApiService; // نمونه‌ای از سرویس API تلگرام

    public function __construct(int $chatId, ?PDO $pdo, Logger $logger, array $config, ?array $user = null)
    {
        $this->chatId = $chatId;
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->user = $user;
        if ($user && isset($user['id'])) {
            $this->userId = $user['id'];
        }

        // ایجاد نمونه از سرویس API تلگرام
        $this->telegramApiService = new TelegramApiService($config['bot_token'], $config['api_url'], $logger);
    }

    /**
     * متد اصلی که توسط روتر فراخوانی می‌شود.
     * هر کلاس فرزند باید این متد را پیاده‌سازی کند.
     */
    abstract public function handle(): void;

    // متدهای کمکی مشترک دیگر می‌توانند در اینجا اضافه شوند،
    // مثلاً متدهایی برای ذخیره وضعیت کاربر (state management) یا دسترسی به داده‌های کاربر از دیتابیس.
}