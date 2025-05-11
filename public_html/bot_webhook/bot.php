<?php
// مسیر فایل روی سرور: /home/cp63882401779/public_html/bot.massagebook.ir/public_html/bot_webhook/bot.php

// تنظیمات اولیه برای نمایش خطاها (در طول توسعه)
error_reporting(E_ALL);
ini_set('display_errors', 1); // در محیط پروداکشن این را 0 کنید

// BOT_ROOT به ریشه پروژه شما اشاره می‌کند: /home/cp63882401779/public_html/bot.massagebook.ir/
define('BOT_ROOT', dirname(dirname(__DIR__)));

// --- Autoloader اصلاح شده ---
spl_autoload_register(function ($className) {
    // $className مثال: "MyTelegramBot\AppCore\Helpers\Logger"
    $projectRootNamespace = 'MyTelegramBot\\'; // پیشوند Namespace اصلی پروژه شما
    $baseDir = BOT_ROOT . '/';                 // مسیر ریشه پروژه روی سرور

    // بررسی اینکه آیا کلاس در Namespace پروژه ما قرار دارد
    if (strncmp($projectRootNamespace, $className, strlen($projectRootNamespace)) !== 0) {
        return; // اگر نیست، Autoloader کاری با آن ندارد
    }

    // حذف پیشوند Namespace اصلی ("MyTelegramBot\") از نام کلاس
    // تا مسیر نسبی به فایل کلاس به دست آید.
    // مثال: "AppCore\Helpers\Logger"
    $classPathWithoutRootNs = substr($className, strlen($projectRootNamespace));

    // تبدیل جداکننده Namespace به جداکننده دایرکتوری و اضافه کردن پسوند .php
    // مثال: /home/.../bot.massagebook.ir/AppCore/Helpers/Logger.php
    $filePath = $baseDir . str_replace('\\', '/', $classPathWithoutRootNs) . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        // لاگ کردن خطا در صورت پیدا نشدن فایل کلاس برای دیباگ
        error_log(
            "Autoloader Error: Class {$className} not found. " .
            "Attempted to load: {$filePath}. " .
            "BOT_ROOT: " . BOT_ROOT . ". " .
            "Current working directory: " . getcwd()
        );
        // برای دیباگ مستقیم وب‌هوک (اگر خطاها نمایش داده شوند)
        // echo "Autoloader Error: Class {$className} not found. Attempted to load: {$filePath}<br>";
    }
});

// --- بارگذاری تنظیمات برنامه ---
$configPath = BOT_ROOT . '/AppCore/Config/app.php';
if (!file_exists($configPath)) {
    http_response_code(500); // خطای سرور
    $fatalError = "FATAL ERROR: Configuration file not found at {$configPath}. Bot cannot start.";
    error_log($fatalError);
    echo $fatalError; // نمایش خطا در خروجی (برای دیباگ اولیه وب‌هوک)
    exit;
}
$config = require $configPath;

// --- مقداردهی اولیه Logger ---
// مسیر فایل لاگ از کانفیگ خوانده می‌شود یا یک مقدار پیش‌فرض دارد
$logFile = $config['log_file'] ?? (BOT_ROOT . '/AppCore/Logs/bot_fallback.log');
$adminChatId = $config['admin_chat_id'] ?? '';
$botToken = $config['bot_token'] ?? ''; // اطمینان از وجود توکن برای لاگر
$apiUrl = $config['api_url'] ?? 'https://api.telegram.org/bot'; // اطمینان از وجود URL برای لاگر

// ایجاد دایرکتوری لاگ اگر وجود نداشته باشد
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) { // 0775 برای امنیت بهتر
        http_response_code(500);
        $fatalError = "FATAL ERROR: Could not create log directory: {$logDir}.";
        error_log($fatalError);
        echo $fatalError;
        exit;
    }
}

try {
    // اطمینان از اینکه کلاس Logger قبل از استفاده، لود شده است
    if (!class_exists('MyTelegramBot\AppCore\Helpers\Logger')) {
         throw new \Exception('Logger class could not be autoloaded before instantiation.');
    }
    $logger = new MyTelegramBot\AppCore\Helpers\Logger(
        $logFile,
        $adminChatId,
        $botToken,
        $apiUrl
    );
} catch (\Throwable $e) { // گرفتن هر نوع خطای قابل پرتاب
    http_response_code(500);
    // این پیام قبل از مقداردهی کامل لاگر ممکن است رخ دهد
    $errorMessage = "FATAL ERROR: Could not initialize Logger. Error: " . $e->getMessage();
    if (isset($e->getFile)) $errorMessage .= " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($errorMessage);
    // اگر لاگر تا حدی ساخته شده بود ولی در ادامه کارش خطا داد (کمتر محتمل برای constructor)
    if (isset($logger) && $logger instanceof MyTelegramBot\AppCore\Helpers\Logger) {
        $logger->critical("FATAL ERROR: Logger initialization failed. Error: " . $e->getMessage(), $e);
    }
    echo $errorMessage; // نمایش خطا در خروجی
    exit;
}

// --- تنظیم مدیریت خطاهای عمومی با استفاده از لاگر ---
set_exception_handler(function (\Throwable $exception) use ($logger) {
    $logger->critical("Unhandled Exception: " . $exception->getMessage(), $exception);
});

set_error_handler(function ($severity, $message, $file, $line) use ($logger) {
    if (!(error_reporting() & $severity)) {
        return false; // این خطا توسط error_reporting نادیده گرفته شده است
    }
    $logger->error("PHP Error: [$severity] $message in $file on line $line");
    return true; // جلوگیری از هندلر خطای پیش‌فرض PHP
});

register_shutdown_function(function () use ($logger) {
    $lastError = error_get_last();
    // بررسی خطاهای Fatal که اسکریپت را متوقف می‌کنند
    if ($lastError && in_array($lastError['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
        $logger->critical(
            "Fatal Shutdown Error: [{$lastError['type']}] {$lastError['message']} in {$lastError['file']} on line {$lastError['line']}"
        );
    }
});

$logger->info("Webhook script started. BOT_ROOT is: " . BOT_ROOT); // لاگ کردن BOT_ROOT برای اطمینان

// --- دریافت آپدیت از تلگرام ---
$updateJson = file_get_contents('php://input');
if (empty($updateJson)) {
    $logger->warning("No input received from Telegram.");
    http_response_code(200); // تلگرام انتظار پاسخ 200 دارد
    echo "OK. No input.";
    exit;
}

$update = json_decode($updateJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->error("Failed to decode JSON input: " . json_last_error_msg() . ". Raw input: " . substr($updateJson, 0, 500));
    http_response_code(400); // Bad Request
    echo "Error decoding JSON.";
    exit;
}

$logger->info("Update received: " . substr($updateJson, 0, 1000) . (strlen($updateJson) > 1000 ? '...' : '')); // خلاصه آپدیت


// --- اتصال به دیتابیس ---
$dbConfig = [
    'db_host'    => $config['db_host'] ?? 'localhost',
    'db_name'    => $config['db_name'] ?? '',
    'db_user'    => $config['db_user'] ?? '',
    'db_pass'    => $config['db_pass'] ?? '',
    'db_charset' => $config['db_charset'] ?? 'utf8mb4',
];

if (empty($dbConfig['db_name']) || empty($dbConfig['db_user'])) {
    $logger->critical("Database configuration (name or user) is missing. Bot cannot proceed with database operations.");
    http_response_code(200); // به تلگرام OK می‌دهیم ولی عملکرد داخلی مختل شده
    echo "OK. DB Config Error.";
    exit;
}

// ارسال لاگر اصلی برای لاگ کردن خطاهای اتصال دیتابیس
$databaseHandler = new MyTelegramBot\AppCore\Database\DatabaseHandler($dbConfig, $logger);
$pdo = $databaseHandler->getConnection();

if ($pdo === null) {
    // پیام خطا قبلاً توسط DatabaseHandler لاگ شده است
    $logger->critical("Database connection failed. Bot cannot proceed with database operations (verified in bot.php).");
    http_response_code(200);
    echo "OK. DB Connection Error.";
    exit;
}
$logger->info("Database connection established successfully.");


// --- مقداردهی اولیه و اجرای روتر ---
if ($update) { // فقط اگر آپدیت معتبری دریافت شده باشد
    try {
        if (!class_exists('MyTelegramBot\AppCore\Handlers\Router')) {
             throw new \Exception('Router class could not be autoloaded before instantiation.');
        }
        $router = new MyTelegramBot\AppCore\Handlers\Router($update, $pdo, $logger, $config);
        $router->route(); // پردازش آپدیت
    } catch (\Throwable $e) {
        $errorMessage = "Error during routing or component execution: " . $e->getMessage();
        if (isset($e->getFile)) $errorMessage .= " in " . $e->getFile() . " on line " . $e->getLine();
        $logger->critical($errorMessage, $e);
        // در صورت تمایل به ارسال پیام خطا به ادمین:
        // $logger->sendToAdmin("🚨 CRITICAL (Router/Component): " . $e->getMessage());
    }
}

// --- ارسال پاسخ موفقیت به تلگرام ---
http_response_code(200);
echo "OK"; // این "OK" برای تلگرام است و به کاربر نمایش داده نمی‌شود.

$logger->info("Webhook script finished successfully.");

?>