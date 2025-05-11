<?php
// MyTelegramBot/AppCore/Handlers/Router.php

namespace MyTelegramBot\AppCore\Handlers;

use MyTelegramBot\AppCore\Helpers\Logger;
use MyTelegramBot\AppCore\Services\TelegramApiService; // برای ارسال پیام‌ها و ...
use PDO; // برای ارسال به کامپوننت‌ها در صورت نیاز

// کامپوننت‌ها / هندلرها
use MyTelegramBot\AppCore\Components\Dice\DiceHandler;
// مثال برای کامپوننت شروع (در آینده)
// use MyTelegramBot\AppCore\Components\Start\StartHandler;
// مثال برای کامپوننت حساب کاربری (در آینده)
// use MyTelegramBot\AppCore\Components\Account\AccountHandler;
// ... و غیره

class Router
{
    protected array $update;
    protected ?PDO $pdo;
    protected Logger $logger;
    protected array $config;
    protected TelegramApiService $telegramApiService; // سرویس برای تعامل با API تلگرام

    public function __construct(array $update, ?PDO $pdo, Logger $logger, array $config)
    {
        $this->update = $update;
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;

        // ایجاد نمونه از سرویس API تلگرام
        $this->telegramApiService = new TelegramApiService($config['bot_token'], $config['api_url'], $logger);
    }

    public function route(): void
    {
        $this->logger->info("Routing update: " . json_encode($this->update));

        // تشخیص نوع آپدیت و هدایت به هندلر مناسب
        if (isset($this->update['message'])) {
            $message = $this->update['message'];
            $chatId = $message['chat']['id'];
            $fromUser = $message['from']; // اطلاعات کاربر فرستنده (آرایه)

            // بررسی وجود متن در پیام
            if (isset($message['text'])) {
                $text = $message['text'];
                $this->logger->info("Processing text message: '{$text}' from user_id: {$fromUser['id']} in chat_id: {$chatId}");

                // تشخیص دستورات (شروع با /)
                if (strpos($text, '/') === 0) {
                    $this->handleCommand($text, $chatId, $fromUser, $message);
                } else {
                    // پیام متنی عادی (نه دستور)
                    $this->handleTextMessage($text, $chatId, $fromUser, $message);
                }
            } elseif (isset($message['dice'])) {
                // اگر پیام حاوی یک تاس است (مثلاً اگر کاربر خودش تاس انداخته باشد)
                $this->logger->info("Received a dice message from user_id: {$fromUser['id']} in chat_id: {$chatId}");
                // $diceHandler = new DiceHandler($chatId, $this->pdo, $this->logger, $this->config, $fromUser);
                // $diceHandler->handleUserDice($message['dice']); // اگر بخواهیم تاس خود کاربر را مدیریت کنیم
                $this->telegramApiService->sendMessage($chatId, "شما یک تاس با مقدار " . $message['dice']['value'] . " انداختید. برای بازی با ربات از دکمه '🎲 | تاس بندازید' استفاده کنید.");

            } else {
                // انواع دیگر پیام‌ها (عکس، ویدیو، و ...)
                $this->logger->info("Received a non-text/non-dice message from user_id: {$fromUser['id']} in chat_id: {$chatId}");
                $this->telegramApiService->sendMessage($chatId, "من فعلاً فقط پیام‌های متنی و دستورات را متوجه می‌شوم.");
            }

        } elseif (isset($this->update['callback_query'])) {
            $callbackQuery = $this->update['callback_query'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $fromUser = $callbackQuery['from']; // اطلاعات کاربر فرستنده
            $data = $callbackQuery['data']; // داده ارسال شده از دکمه

            $this->logger->info("Processing callback_query: '{$data}' from user_id: {$fromUser['id']} in chat_id: {$chatId}");
            $this->handleCallbackQuery($data, $chatId, $fromUser, $callbackQuery);

        } else {
            $this->logger->warning("Unsupported update type received: " . json_encode(array_keys($this->update)));
        }
    }

    protected function handleCommand(string $command, int $chatId, array $fromUser, array $messageContext): void
    {
        // جدا کردن دستور از آرگومان‌ها (اگر وجود داشته باشند)
        // $parts = explode(' ', $command, 2);
        // $baseCommand = $parts[0];
        // $argument = $parts[1] ?? null;

        switch ($command) {
            case '/start':
                $this->logger->info("Command /start received by user_id: {$fromUser['id']}.");
                // در آینده می‌توانیم یک StartHandler جداگانه برای ثبت کاربر جدید و ... داشته باشیم
                // $startHandler = new StartHandler($chatId, $this->pdo, $this->logger, $this->config, $fromUser);
                // $startHandler->handle();
                $this->telegramApiService->sendMainMenu($chatId, "به ربات من خوش آمدید! چه کاری می‌توانم برایتان انجام دهم؟");
                break;
            // case '/help':
                // $helpHandler = new HelpHandler(...);
                // $helpHandler->handle();
                // break;
            default:
                $this->logger->info("Unknown command: {$command} from user_id: {$fromUser['id']}");
                $this->telegramApiService->sendMessage($chatId, "دستور '{$command}' را نمی‌شناسم.");
                break;
        }
    }

    protected function handleTextMessage(string $text, int $chatId, array $fromUser, array $messageContext): void
    {
        $this->logger->info("Handling text message: '{$text}' from user_id: {$fromUser['id']}");

        // تطبیق متن با گزینه‌های منو
        // این روش برای منوهای متنی ساده کار می‌کند. برای دکمه‌های شیشه‌ای از callback_data استفاده می‌کنیم.
        switch ($text) {
            case '🎲 | تاس بندازید':
                $diceHandler = new DiceHandler($chatId, $this->pdo, $this->logger, $this->config, $fromUser);
                $diceHandler->handle(); // فراخوانی متد اصلی کامپوننت تاس
                break;
            case '💰 | شارژ حساب':
                // $accountHandler = new AccountHandler($chatId, $this->pdo, $this->logger, $this->config, $fromUser);
                // $accountHandler->handleChargeRequest();
                $this->telegramApiService->sendMessage($chatId, "شما 'شارژ حساب' را انتخاب کردید. (کامپوننت هنوز پیاده‌سازی نشده)");
                break;
            case '💳 | برداشت موجودی':
                // $accountHandler = new AccountHandler(...);
                // $accountHandler->handleWithdrawRequest();
                $this->telegramApiService->sendMessage($chatId, "شما 'برداشت موجودی' را انتخاب کردید. (کامپوننت هنوز پیاده‌سازی نشده)");
                break;
            case '👤 | حساب من':
                // $accountHandler = new AccountHandler(...);
                // $accountHandler->showAccountInfo();
                $this->telegramApiService->sendMessage($chatId, "شما 'حساب من' را انتخاب کردید. (کامپوننت هنوز پیاده‌سازی نشده)");
                break;
            case '🔗 | زیرمجموعه گیری':
                // $referralHandler = new ReferralHandler(...);
                // $referralHandler->handle();
                $this->telegramApiService->sendMessage($chatId, "شما 'زیرمجموعه گیری' را انتخاب کردید. (کامپوننت هنوز پیاده‌سازی نشده)");
                break;
            case '💬 | ارتباط با پشتیبانی':
                // $supportHandler = new SupportHandler(...);
                // $supportHandler->handle();
                $this->telegramApiService->sendMessage($chatId, "شما 'ارتباط با پشتیبانی' را انتخاب کردید. (کامپوننت هنوز پیاده‌سازی نشده)");
                break;
            case '🔰 | چطور اعتماد کنم؟':
                // $faqHandler = new FaqHandler(...);
                // $faqHandler->showTrustInfo();
                $this->telegramApiService->sendMessage($chatId, "شما 'چطور اعتماد کنم؟' را انتخاب کردید. (کامپوننت هنوز پیاده‌سازی نشده)");
                break;
            default:
                // اگر متن پیام با هیچ یک از گزینه‌های منو مطابقت نداشت
                $this->logger->info("Unhandled text message: '{$text}' from user_id: {$fromUser['id']}. Showing main menu.");
                $this->telegramApiService->sendMainMenu($chatId, "متوجه پیام شما نشدم. لطفاً از گزینه‌های زیر استفاده کنید:");
                break;
        }
    }

    protected function handleCallbackQuery(string $data, int $chatId, array $fromUser, array $callbackQueryContext): void
    {
        $this->logger->info("Handling callback_query with data: '{$data}' from user_id: {$fromUser['id']}");

        // مثال برای مدیریت callback_data در آینده:
        // list($component, $action) = explode(':', $data, 2);
        // switch ($component) {
        //     case 'dice':
        //         if ($action === 'roll_again') {
        //             $diceHandler = new DiceHandler($chatId, $this->pdo, $this->logger, $this->config, $fromUser);
        //             $diceHandler->handle();
        //         }
        //         break;
        //     case 'account':
        //         // ...
        //         break;
        // }

        $this->telegramApiService->sendMessage($chatId, "شما یک دکمه با داده '{$data}' را فشار دادید. (هنوز مدیریت نشده)");

        // فراموش نکنید که به callback_query پاسخ دهید تا علامت لودینگ روی دکمه از بین برود
        $this->telegramApiService->answerCallbackQuery($callbackQueryContext['id']);
    }
}