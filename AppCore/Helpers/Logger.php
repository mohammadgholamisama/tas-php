<?php
// AppCore/Helpers/Logger.php

namespace MyTelegramBot\AppCore\Helpers; // Namespace اصلی پروژه را حفظ می‌کنیم

class Logger
{
    private string $logFile;

    public function __construct(string $logFileFullPath)
    {
        $this->logFile = $logFileFullPath;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
                // اگر نتوانست بسازد، حداقل یک خطا در لاگ پیش‌فرض PHP ثبت شود
                error_log("Logger Error: Could not create log directory: {$logDir}");
            }
        }
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    public function critical(string $message): void
    {
        $this->writeLog('CRITICAL', $message);
    }
}
?>