<?php
// AppCore/Config/app.php

return [
    // --- Telegram Bot Settings ---
    'bot_token'       => '7898824597:AAE4sbcR-9YuqnFH14QLmGqiqI_qPxF7Wa0', // <<< توکن ربات شما
    'api_url'         => 'https://api.telegram.org/bot',
    'webhook_url'     => 'https://bot.massagebook.ir/bot_webhook/bot.php', // <<< URL نهایی وب‌هوک شما
    'admin_chat_id'   => '243761094', // <<< شناسه چت ادمین شما

    // --- Logging Settings ---
    // مسیر فایل لاگ نسبت به BOT_ROOT (که در bot.php تعریف می‌شود)
    'log_file'        => 'AppCore/Logs/bot.log',
];
?>