<?php
// MyTelegramBot/public_html/bot_webhook/bot.php

// برای افزایش سطح امنیت، گزارش خطاها را در محیط پروداکشن غیرفعال کنید
// و فقط از طریق لاگر خطاها را ثبت کنید.
// error_reporting(0);
// ini_set('display_errors', 0);
// در طول توسعه می‌توانید این‌ها را فعال نگه دارید:
error_reporting(E_ALL);
ini_set('display_errors', 1);


define('BOT_ROOT', dirname(dirname(__DIR__))); // MyTelegramBot/

// --- Autoloader ساده (برای شروع، بعداً می‌توان از Composer استفاده کرد) ---
spl_autoload_register(function ($className) {
    $baseNamespace = 'MyTelegramBot\\';
    $baseDir = BOT_ROOT . '/';

    if (strncmp($baseNamespace, $className, strlen($baseNamespace)) !== 0) {
        return;
    }
    $relativeClassName = substr($className, strlen($baseNamespace));
    $filePath = $baseDir . str_replace('\\', '/', $relativeClassName) . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        // error_log("Autoloader: Could not load class {$className}. File not found: {$filePath}");
    }
});


// --- بارگذاری تنظیمات ---
$configPath = BOT_ROOT . '/AppCore/Config/app.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    $fatalError = "FATAL ERROR: Configuration file not found at {$configPath}. Bot cannot start.";
    error_log($fatalError);
    echo $fatalError;
    exit;
}
$config = require $configPath;


// --- مقداردهی اولیه لاگر ---
$logFile = $config['log_file'] ?? BOT_ROOT . '/AppCore/Logs/fallback_bot.log';
$adminChatId = $config['admin_chat_id'] ?? '';
$botToken = $config['bot_token'] ?? '';
$apiUrl = $config['api_url'] ?? 'https://api.telegram.org/bot';

$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

try {
    $logger = new MyTelegramBot\AppCore\Helpers\Logger(
        $logFile,
        $adminChatId,
        $botToken,
        $apiUrl
    );
} catch (\Throwable $e) {
    http_response_code(500);
    $fatalError = "FATAL ERROR: Could not initialize Logger. Error: " . $e->getMessage();
    error_log($fatalError);
    if (isset($logger) && $logger instanceof MyTelegramBot\AppCore\Helpers\Logger) {
        $logger->critical("FATAL ERROR: Post-Logger-Initialization. Error: " . $e->getMessage(), $e);
    }
    echo $fatalError;
    exit;
}


// --- تنظیم مدیریت خطاهای عمومی با استفاده از لاگر ---
set_exception_handler(function (\Throwable $exception) use ($logger) {
    $logger->critical("Unhandled Exception: " . $exception->getMessage(), $exception);
    // در محیط پروداکشن، بهتر است هیچ خروجی به کاربر نمایش داده نشود
    // http_response_code(500);
    // echo "An internal server error occurred.";
});

set_error_handler(function ($severity, $message, $file, $line) use ($logger) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $logger->error("PHP Error: [$severity] $message in $file on line $line");
    return true;
});

register_shutdown_function(function () use ($logger) {
    $lastError = error_get_last();
    if ($lastError && in_array($lastError['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
        $logger->critical(
            "Fatal Shutdown Error: [{$lastError['type']}] {$lastError['message']} in {$lastError['file']} on line {$lastError['line']}"
        );
    }
});

$logger->info("Webhook script started.");

// --- دریافت آپدیت از تلگرام ---
$updateJson = file_get_contents('php://input');
if (empty($updateJson)) {
    $logger->warning("No input received from Telegram.");
    http_response_code(200);
    echo "OK. No input.";
    exit;
}

$update = json_decode($updateJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->error("Failed to decode JSON input: " . json_last_error_msg() . ". Raw input: " . substr($updateJson, 0, 500));
    http_response_code(400);
    echo "Error decoding JSON.";
    exit;
}

$logger->info("Update received: " . $updateJson);


// --- اتصال به دیتابیس ---
$dbConfig = [
    'db_host'    => $config['db_host'],
    'db_name'    => $config['db_name'],
    'db_user'    => $config['db_user'],
    'db_pass'    => $config['db_pass'],
    'db_charset' => $config['db_charset'],
];

// شما می‌توانید یک لاگر جدا برای دیتابیس داشته باشید یا از لاگر اصلی استفاده کنید
// $dbLoggerFile = $config['log_db_conn_file'] ?? $logFile;
// $dbConnectionLogger = new MyTelegramBot\AppCore\Helpers\Logger($dbLoggerFile, $adminChatId, $botToken, $apiUrl, false);
// برای سادگی، از لاگر اصلی با سطح پایین‌تر استفاده می‌کنیم یا اصلاً لاگ اتصال موفق را نمی‌زنیم مگر در حالت دیباگ
$databaseHandler = new MyTelegramBot\AppCore\Database\DatabaseHandler($dbConfig, $logger); // ارسال لاگر اصلی
$pdo = $databaseHandler->getConnection();

if ($pdo === null) {
    $logger->critical("Failed to connect to the database. Bot cannot proceed with database operations.");
    http_response_code(200);
    echo "OK. DB Error.";
    exit;
}
$logger->info("Database connection successful.");


// --- مقداردهی اولیه و اجرای روتر ---
if ($update) { // فقط اگر آپدیت معتبری دریافت شده باشد
    try {
        $router = new MyTelegramBot\AppCore\Handlers\Router($update, $pdo, $logger, $config);
        $router->route(); // پردازش آپدیت
    } catch (\Throwable $e) {
        // اگر در خود روتر یا کامپوننت‌ها خطای جدی رخ دهد
        $logger->critical("Error during routing or component execution: " . $e->getMessage(), $e);
        // $logger->sendToAdmin("🚨 CRITICAL (Router/Component): " . $e->getMessage()); // اگر می‌خواهید به ادمین هم پیام دهید

        // به کاربر یک پیام عمومی خطا بدهید (اختیاری و با احتیاط)
        // این کار باید با دقت انجام شود تا اطلاعات حساس لو نرود
        // $chatIdForError = null;
        // if (isset($update['message']['chat']['id'])) {
        //     $chatIdForError = $update['message']['chat']['id'];
        // } elseif (isset($update['callback_query']['message']['chat']['id'])) {
        //     $chatIdForError = $update['callback_query']['message']['chat']['id'];
        // }
        //
        // if ($chatIdForError && isset($router) && $router instanceof MyTelegramBot\AppCore\Handlers\Router) {
        //     // $router->sendTextMessage($chatIdForError, "متاسفانه مشکلی در پردازش درخواست شما پیش آمده است. لطفاً بعداً تلاش کنید.");
        // }
    }
}


// --- ارسال پاسخ موفقیت به تلگرام ---
// تلگرام انتظار پاسخ HTTP 200 OK را دارد تا بداند وب‌هوک شما آپدیت را دریافت کرده است.
// خود پاسخ به کاربر از طریق متدهای API تلگرام (مثل sendMessage) ارسال می‌شود.
http_response_code(200);
echo "OK"; // این "OK" برای تلگرام است و به کاربر نمایش داده نمی‌شود.

$logger->info("Webhook script finished successfully.");

?>