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
// این اتولودر ساده به جای require_once های متعدد استفاده می‌شود.
// فرض بر این است که ساختار پوشه و namespace ها با هم مطابقت دارند.
// مثال: MyTelegramBot\AppCore\Helpers\Logger -> MyTelegramBot/AppCore/Helpers/Logger.php
spl_autoload_register(function ($className) {
    // مسیر پایه برای namespace های برنامه ما
    $baseNamespace = 'MyTelegramBot\\';
    $baseDir = BOT_ROOT . '/'; // ریشه پروژه

    // اگر کلاس به namespace برنامه ما تعلق ندارد، کاری انجام نده
    if (strncmp($baseNamespace, $className, strlen($baseNamespace)) !== 0) {
        return;
    }

    // حذف پیشوند namespace پایه
    $relativeClassName = substr($className, strlen($baseNamespace));

    // جایگزینی جداکننده namespace (\) با جداکننده دایرکتوری (/) و اضافه کردن .php
    $filePath = $baseDir . str_replace('\\', '/', $relativeClassName) . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        // برای دیباگ، اگر فایل پیدا نشد، می‌توانید یک خطا لاگ کنید یا نمایش دهید
        // error_log("Autoloader: Could not load class {$className}. File not found: {$filePath}");
    }
});


// --- بارگذاری تنظیمات ---
$configPath = BOT_ROOT . '/AppCore/Config/app.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    // این پیام قبل از مقداردهی لاگر است، پس مستقیماً echo می‌شود یا در error_log سرور ثبت می‌شود
    $fatalError = "FATAL ERROR: Configuration file not found at {$configPath}. Bot cannot start.";
    error_log($fatalError);
    echo $fatalError; // برای اینکه ادمین از طریق وب‌هوک هم متوجه شود (اگر وب‌هوک را مستقیم باز کند)
    exit;
}
$config = require $configPath;


// --- مقداردهی اولیه لاگر ---
// اطمینان حاصل کنید که کلیدهای مورد نیاز در کانفیگ وجود دارند
$logFile = $config['log_file'] ?? BOT_ROOT . '/AppCore/Logs/fallback_bot.log';
$adminChatId = $config['admin_chat_id'] ?? '';
$botToken = $config['bot_token'] ?? '';
$apiUrl = $config['api_url'] ?? 'https://api.telegram.org/bot';

// ایجاد دایرکتوری لاگ اگر لاگر نتواند آن را بسازد (برای اطمینان)
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
    // اگر حتی ساخت لاگر هم خطا داد
    http_response_code(500);
    $fatalError = "FATAL ERROR: Could not initialize Logger. Error: " . $e->getMessage();
    error_log($fatalError);
    if (isset($logger) && $logger instanceof MyTelegramBot\AppCore\Helpers\Logger) {
        // اگر لاگر ساخته شده بود ولی در ادامه کارش خطا داد
        $logger->critical("FATAL ERROR: Post-Logger-Initialization. Error: " . $e->getMessage(), $e);
    }
    echo $fatalError; // نمایش خطا در خروجی (برای دیباگ اولیه وب‌هوک)
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
        // This error code is not included in error_reporting
        return false;
    }
    $logger->error("PHP Error: [$severity] $message in $file on line $line");
    return true; // از هندلر خطای پیش‌فرض PHP جلوگیری می‌کند
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
    http_response_code(200); // تلگرام انتظار پاسخ 200 دارد حتی برای آپدیت خالی
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

$logger->info("Update received: " . $updateJson); // لاگ کردن آپدیت کامل (برای دیباگ)
// $logger->debug("Decoded update object: ", $update); // برای لاگ کردن آبجکت دیکد شده


// --- اتصال به دیتابیس ---
$dbConfig = [
    'db_host'    => $config['db_host'],
    'db_name'    => $config['db_name'],
    'db_user'    => $config['db_user'],
    'db_pass'    => $config['db_pass'],
    'db_charset' => $config['db_charset'],
];

$dbLoggerFile = $config['log_db_conn_file'] ?? $logFile; // استفاده از لاگ فایل اصلی اگر لاگ دیتابیس جدا تعریف نشده
$dbConnectionLogger = new MyTelegramBot\AppCore\Helpers\Logger($dbLoggerFile, $adminChatId, $botToken, $apiUrl, false); // ارسال به تلگرام برای لاگ دیتابیس غیرفعال است

$databaseHandler = new MyTelegramBot\AppCore\Database\DatabaseHandler($dbConfig, $dbConnectionLogger);
$pdo = $databaseHandler->getConnection();

if ($pdo === null) {
    $logger->critical("Failed to connect to the database. Bot cannot proceed with database operations.");
    // در اینجا می‌توانید تصمیم بگیرید که آیا ربات باید کاملاً متوقف شود یا برخی عملکردها بدون دیتابیس ادامه یابند
    // برای شروع، فرض می‌کنیم دیتابیس برای اکثر عملکردها ضروری است.
    // پاسخ به تلگرام ارسال شده، پس فقط اسکریپت را خاتمه می‌دهیم.
    http_response_code(200); // به تلگرام OK می‌دهیم ولی عملکرد داخلی مختل شده
    echo "OK. DB Error."; // این پیام به تلگرام ارسال نمی‌شود، فقط برای لاگ یا اگر مستقیم باز شود
    exit;
}
$logger->info("Database connection successful.");


// --- در اینجا Router یا Main Controller قرار می‌گیرد ---
// TODO: ایجاد یک کلاس روتر برای هدایت آپدیت به کامپوننت/هندلر مناسب
// $router = new MyTelegramBot\AppCore\Router($update, $pdo, $logger, $config);
// $router->route();

// --- مثال اولیه برای پاسخ ---
// این بخش باید توسط روتر و کامپوننت‌ها مدیریت شود
// $chatId = $update['message']['chat']['id'] ?? null;
// if ($chatId) {
//     $text = $update['message']['text'] ?? 'No text';
//     $responseMessage = "You said: " . htmlspecialchars($text);
//     // sendMessage($chatId, $responseMessage, $config['bot_token'], $config['api_url']);
//     $logger->info("Responded to chat_id: {$chatId}");
// }


// --- ارسال پاسخ موفقیت به تلگرام ---
// تلگرام انتظار پاسخ HTTP 200 OK را دارد تا بداند وب‌هوک شما آپدیت را دریافت کرده است.
// خود پاسخ به کاربر از طریق متدهای API تلگرام (مثل sendMessage) ارسال می‌شود.
http_response_code(200);
echo "OK"; // این "OK" برای تلگرام است و به کاربر نمایش داده نمی‌شود.

$logger->info("Webhook script finished successfully.");

?>