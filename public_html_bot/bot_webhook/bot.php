<?php
// public_html_bot/bot_webhook/bot.php

error_reporting(E_ALL);
ini_set('display_errors', 1); // برای دیباگ اولیه، خطاها را نمایش بده

// BOT_ROOT به ریشه پروژه شما روی سرور اشاره می‌کند:
// /home/cp63882401779/public_html/bot.massagebook.ir/
define('BOT_ROOT', dirname(dirname(__DIR__)));

// --- Autoloader اصلاح شده ---
spl_autoload_register(function ($className) {
    $projectRootNamespace = 'MyTelegramBot\\';
    $baseDir = BOT_ROOT . '/';

    if (strncmp($projectRootNamespace, $className, strlen($projectRootNamespace)) !== 0) {
        return;
    }
    $classPathWithoutRootNs = substr($className, strlen($projectRootNamespace));
    $filePath = $baseDir . str_replace('\\', '/', $classPathWithoutRootNs) . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        error_log("Autoloader Error: Class {$className} not found. Attempted to load: {$filePath}. BOT_ROOT: " . BOT_ROOT);
    }
});

// --- بارگذاری تنظیمات ---
$configPath = BOT_ROOT . '/AppCore/Config/app.php';
$logger = null; // تعریف اولیه لاگر

if (!file_exists($configPath)) {
    http_response_code(500);
    $fatalError = "FATAL ERROR: Config file not found at {$configPath}.";
    error_log($fatalError);
    echo $fatalError;
    exit;
}
$config = require $configPath;

// --- مقداردهی اولیه Logger ---
try {
    // مسیر فایل لاگ را از کانفیگ می‌خوانیم و با BOT_ROOT ترکیب می‌کنیم
    $logFilePath = BOT_ROOT . '/' . ($config['log_file'] ?? 'AppCore/Logs/default_bot.log');

    if (!class_exists('MyTelegramBot\AppCore\Helpers\Logger')) {
        throw new \Exception('Logger class could not be autoloaded before instantiation.');
    }
    $logger = new MyTelegramBot\AppCore\Helpers\Logger($logFilePath);
    $logger->info("Logger initialized successfully. Log file: " . $logFilePath);

} catch (\Throwable $e) {
    http_response_code(500);
    $errorMessage = "FATAL ERROR: Could not initialize Logger. Error: " . $e->getMessage();
    error_log($errorMessage);
    // اگر لاگر تا حدی ساخته شده بود
    if (isset($logger) && $logger instanceof MyTelegramBot\AppCore\Helpers\Logger) {
        $logger->critical("Logger initialization failed during catch: " . $e->getMessage());
    }
    echo $errorMessage;
    exit;
}

$logger->info("Webhook script started. BOT_ROOT: " . BOT_ROOT);

// --- دریافت آپدیت از تلگرام ---
$updateJson = file_get_contents('php://input');
if (empty($updateJson)) {
    if ($logger) $logger->info("No input received from Telegram.");
    http_response_code(200);
    echo "OK. No input.";
    exit;
}

$update = json_decode($updateJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    if ($logger) $logger->error("Failed to decode JSON input: " . json_last_error_msg() . ". Raw: " . substr($updateJson, 0, 200));
    http_response_code(400);
    echo "Error decoding JSON.";
    exit;
}

if ($logger) $logger->info("Update received: " . substr($updateJson, 0, 500));

// --- پردازش ساده آپدیت و ارسال پاسخ ---
if (isset($update['message']['text']) && isset($update['message']['chat']['id'])) {
    $chatId = $update['message']['chat']['id'];
    $receivedText = $update['message']['text'];
    $responseText = "شما گفتید: " . htmlspecialchars($receivedText);

    // ارسال پاسخ با استفاده از cURL (ساده)
    $telegramApiUrl = ($config['api_url'] ?? 'https://api.telegram.org/bot') . ($config['bot_token'] ?? '') . '/sendMessage';
    $postFields = [
        'chat_id' => $chatId,
        'text' => $responseText,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // برای تست در برخی هاست‌ها، در پروداکشن با احتیاط

    $serverOutput = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        if ($logger) $logger->error("cURL Error while sending message: " . $curlError);
    } else {
        if ($logger) $logger->info("Message sent response: " . substr($serverOutput, 0, 200));
    }
}

// --- ارسال پاسخ موفقیت به تلگرام ---
http_response_code(200);
echo "OK";

if ($logger) $logger->info("Webhook script finished.");
?>