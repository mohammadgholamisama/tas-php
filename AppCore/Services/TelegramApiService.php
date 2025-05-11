<?php
// MyTelegramBot/AppCore/Services/TelegramApiService.php

namespace MyTelegramBot\AppCore\Services;

use MyTelegramBot\AppCore\Helpers\Logger;

class TelegramApiService
{
    private string $botToken;
    private string $apiUrl;
    private Logger $logger;

    public function __construct(string $botToken, string $apiUrl, Logger $logger)
    {
        $this->botToken = $botToken;
        $this->apiUrl = $apiUrl;
        $this->logger = $logger;
    }

    /**
     * متد عمومی برای ارسال درخواست به API تلگرام با استفاده از cURL.
     */
    public function sendRequest(string $method, array $params = []): ?array
    {
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true); // اکثر متدهای تلگرام POST هستند
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // تایم‌اوت اتصال
        // curl_setopt($ch, CURLOPT_TIMEOUT, 10);      // تایم‌اوت کل عملیات

        // برای جلوگیری از خطای SSL در برخی هاست‌ها (در محیط پروداکشن واقعی توصیه نمی‌شود مگر با اطمینان)
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $responseJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error("cURL Error for method {$method}: {$curlError}. URL: {$url}, Params: " . json_encode($params));
            return null;
        }

        $responseArray = json_decode($responseJson, true);

        if ($httpCode !== 200 || !isset($responseArray['ok']) || $responseArray['ok'] === false) {
            $this->logger->error(
                "Telegram API Error for method {$method}. HTTP Code: {$httpCode}. " .
                "Response: {$responseJson}. URL: {$url}, Params: " . json_encode($params)
            );
            return null;
        }

        $this->logger->info("Telegram API success for method {$method}. Response: " . substr($responseJson, 0, 500) . (strlen($responseJson) > 500 ? '...' : ''));
        return $responseArray['result'] ?? $responseArray; // 'result' ممکن است همیشه وجود نداشته باشد (مثلاً برای answerCallbackQuery)
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): ?array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->sendRequest('sendMessage', $params);
    }

    public function sendDice(int $chatId, string $emoji = '🎲'): ?array
    {
        // emoji می‌تواند: '🎲', '🎯', '🏀', '⚽', '🎳', '🎰'
        $params = [
            'chat_id' => $chatId,
            'emoji' => $emoji,
        ];
        return $this->sendRequest('sendDice', $params);
    }

    public function sendMainMenu(int $chatId, string $caption): ?array
    {
        $keyboard = [
            'keyboard' => [
                [['text' => '🎲 | تاس بندازید']],
                [['text' => '💰 | شارژ حساب'], ['text' => '💳 | برداشت موجودی']],
                [['text' => '👤 | حساب من'], ['text' => '🔗 | زیرمجموعه گیری']],
                [['text' => '💬 | ارتباط با پشتیبانی'], ['text' => '🔰 | چطور اعتماد کنم؟']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        return $this->sendMessage($chatId, $caption, $keyboard);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): ?array
    {
        return $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }

    // متدهای دیگری مثل editMessageText, deleteMessage, sendPhoto و ... را می‌توانید به همین ترتیب اضافه کنید.
}