<?php
// MyTelegramBot/AppCore/Components/Dice/DiceHandler.php

namespace MyTelegramBot\AppCore\Components\Dice;

use MyTelegramBot\AppCore\Handlers\BaseHandler; // ما یک کلاس پایه برای هندلرها ایجاد خواهیم کرد
use PDO;

class DiceHandler extends BaseHandler // از BaseHandler ارث‌بری خواهد کرد
{
    // اگر نیاز به ذخیره اطلاعات خاصی در مورد تاس در دیتابیس داشتیم، از $pdo استفاده می‌کنیم
    // فعلاً برای تاس انداختن ساده، نیازی به pdo در اینجا نیست، اما برای سازگاری با BaseHandler نگه می‌داریم

    public function __construct(int $chatId, ?PDO $pdo, \MyTelegramBot\AppCore\Helpers\Logger $logger, array $config, ?array $user = null)
    {
        parent::__construct($chatId, $pdo, $logger, $config, $user);
    }

    /**
     * متد اصلی برای زمانی که کاربر گزینه "تاس بندازید" را انتخاب می‌کند.
     */
    public function handle(): void
    {
        $this->logger->info("DiceHandler: User {$this->userId} in chat {$this->chatId} requested to roll a dice.");
        $this->rollAndSend();
    }

    /**
     * یک تاس می‌اندازد و نتیجه را برای کاربر ارسال می‌کند.
     * از متد sendDice خود تلگرام استفاده می‌کند.
     */
    public function rollAndSend(): void
    {
        $this->logger->info("Attempting to send a dice to chat_id: {$this->chatId}");

        // پارامتر emoji برای sendDice می‌تواند '🎲', '🎯', '🏀', '⚽', '🎳', '🎰' باشد.
        // اگر emoji مشخص نشود، تلگرام به صورت پیش‌فرض یک تاس شش وجهی (🎲) می‌فرستد.
        $response = $this->telegramApiService->sendDice($this->chatId, '🎲');

        if ($response && isset($response['dice']['value'])) {
            $diceValue = $response['dice']['value'];
            $this->logger->info("Dice sent successfully to chat_id: {$this->chatId}. Value: {$diceValue}");
            // می‌توانیم یک پیام تکمیلی هم بفرستیم، اگرچه خود sendDice پیام دارد.
            // $this->telegramApiService->sendMessage($this->chatId, "نتیجه تاس شما: " . $diceValue);
        } else {
            $this->logger->error("Failed to send dice or get its value for chat_id: {$this->chatId}");
            $this->telegramApiService->sendMessage($this->chatId, "متاسفانه مشکلی در انداختن تاس پیش آمد. لطفاً دوباره تلاش کنید.");
        }
    }

    /**
     * اگر کاربر خودش یک پیام تاس (dice message) ارسال کند، این متد می‌تواند آن را مدیریت کند.
     * (این متد فعلاً توسط روتر فراخوانی نمی‌شود، اما برای آینده می‌تواند مفید باشد)
     */
    public function handleUserDice(array $diceData): void
    {
        $value = $diceData['value'];
        $emoji = $diceData['emoji'];
        $this->logger->info("User {$this->userId} rolled a '{$emoji}' with value {$value} in chat {$this->chatId}.");
        $this->telegramApiService->sendMessage(
            $this->chatId,
            "شما یک {$emoji} با مقدار {$value} انداختید. برای بازی با ربات از دکمه 'تاس بندازید' استفاده کنید."
        );
    }
}