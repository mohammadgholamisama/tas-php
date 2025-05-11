<?php
// MyTelegramBot/AppCore/Helpers/Logger.php

namespace MyTelegramBot\AppCore\Helpers;

class Logger
{
    private string $logFile;
    private string $adminChatId;
    private string $botToken;
    private string $apiUrl;
    private bool $sendToTelegram;

    public function __construct(string $logFile, string $adminChatId = '', string $botToken = '', string $apiUrl = '', bool $sendToTelegram = true)
    {
        $this->logFile = $logFile;
        $this->adminChatId = $adminChatId;
        $this->botToken = $botToken;
        $this->apiUrl = $apiUrl;
        $this->sendToTelegram = $sendToTelegram && !empty($adminChatId) && !empty($botToken);

        // ایجاد دایرکتوری لاگ اگر وجود نداشته باشد
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true); // 0775 برای امنیت بیشتر در هاست اشتراکی
        }
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // تلاش برای نوشتن در فایل با قفل انحصاری برای جلوگیری از تداخل در写入 همزمان
        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // اگر نوشتن در فایل لاگ اصلی ناموفق بود، تلاش برای لاگ خطا در error_log پیش‌فرض PHP
            error_log("Failed to write to custom log file: {$this->logFile}. Log entry: {$logEntry}");
        }
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message, \Throwable $exception = null): void
    {
        if ($exception) {
            $message .= " | Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
            // $message .= "\nTrace: " . $exception->getTraceAsString(); // برای جزئیات بیشتر، ولی ممکن است لاگ را طولانی کند
        }
        $this->writeLog('ERROR', $message);

        if ($this->sendToTelegram) {
            $this->sendToAdmin("🔴 ERROR: " . substr($message, 0, 1000)); // محدود کردن طول پیام برای تلگرام
        }
    }

    public function debug(string $message, array $context = []): void
    {
        if (!empty($context)) {
            $message .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $this->writeLog('DEBUG', $message);
    }

    public function critical(string $message, \Throwable $exception = null): void
    {
        if ($exception) {
            $message .= " | Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        }
        $this->writeLog('CRITICAL', $message);

        if ($this->sendToTelegram) {
            $this->sendToAdmin("🚨 CRITICAL: " . substr($message, 0, 1000));
        }
    }

    public function sendToAdmin(string $message): void
    {
        if (!$this->sendToTelegram) {
            $this->warning("Attempted to send message to admin, but adminChatId or botToken is not configured.");
            return;
        }

        $url = $this->apiUrl . $this->botToken . '/sendMessage';
        $data = [
            'chat_id' => $this->adminChatId,
            'text' => $message,
            'parse_mode' => 'HTML' // یا Markdown، بسته به فرمت پیام
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true // برای اینکه حتی اگر ارسال ناموفق بود، اسکریپت متوقف نشود
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            $this->error("Failed to send message to admin via Telegram API. URL: $url, Data: " . json_encode($data));
        } else {
            $response = json_decode($result, true);
            if (!isset($response['ok']) || !$response['ok']) {
                $this->error("Telegram API returned an error while sending message to admin: " . ($response['description'] ?? 'Unknown error') . " | Data: " . json_encode($data));
            }
        }
    }
}