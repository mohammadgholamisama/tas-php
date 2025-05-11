<?php
// MyTelegramBot/AppCore/Config/app.php

// مسیر ریشه برنامه شما (یک سطح بالاتر از پوشه config)
// این برای تعیین مسیر لاگ‌ها استفاده می‌شود
define('APP_CORE_ROOT', dirname(__DIR__)); // این به پوشه AppCore اشاره می‌کند

return [
    // --- Telegram Bot Settings ---
    'bot_token'       => '7898824597:AAE4sbcR-9YuqnFH14QLmGqiqI_qPxF7Wa0', // <<< توکن ربات خود را اینجا وارد کنید
    'api_url'         => 'https://api.telegram.org/bot',
    'webhook_url'     => 'https://bot.massagebook.ir/bot_webhook/bot.php', // <<< آدرس وب‌هوک خود را اینجا وارد کنید
    'admin_chat_id'   => '243761094', // <<< شناسه عددی چت ادمین خود را اینجا وارد کنید

    // --- Database Settings ---
    'db_host'         => 'localhost',
    'db_name'         => 'cp63882401779_phpbot_db',    // <<< نام دیتابیس
    'db_user'         => 'cp63882401779_phpbot_usr',    // <<< نام کاربری دیتابیس
    'db_pass'         => 'Mohammad1380',// <<< رمز عبور دیتابیس
    'db_charset'      => 'utf8mb4',

    // --- Logging Settings ---
    // مسیرها نسبت به ریشه پروژه (جایی که composer.json هست) یا یک مسیر مطلق تعریف شوند
    // بهتر است از APP_CORE_ROOT که در بالا تعریف شد استفاده کنیم تا مسیردهی دقیق‌تر باشد.
    'log_file'          => APP_CORE_ROOT . '/logs/bot.log',
    'log_db_conn_file'  => APP_CORE_ROOT . '/logs/db_connection.log', // اگر لاگر جدا برای دیتابیس می‌خواهید

    // --- سایر تنظیمات ربات (می‌توانید بعداً از جدول bot_settings دیتابیس بخوانید) ---
    // 'example_setting_default' => true,
];
