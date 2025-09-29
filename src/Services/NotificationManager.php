<?php

namespace MirzaAghazadeh\SmartFailover\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class NotificationManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected array $throttleCache = [];

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Notify about service failure
     */
    public function notifyFailure(\Exception $exception, array $context = []): void
    {
        if (!$this->config->get('smart-failover.notifications.enabled', false)) {
            return;
        }

        $message = $this->formatFailureMessage($exception, $context);
        $throttleKey = $this->getThrottleKey($exception);

        // Check throttling
        if ($this->isThrottled($throttleKey)) {
            $this->logger->debug('Notification throttled', [
                'throttle_key' => $throttleKey,
                'exception' => $exception->getMessage(),
            ]);
            return;
        }

        // Send notifications
        $this->sendNotifications($message, $context);

        // Set throttle
        $this->setThrottle($throttleKey);
    }

    /**
     * Notify about service recovery
     */
    public function notifyRecovery(string $service, array $context = []): void
    {
        if (!$this->config->get('smart-failover.notifications.enabled', false)) {
            return;
        }

        $message = $this->formatRecoveryMessage($service, $context);
        
        // Send notifications
        $this->sendNotifications($message, $context, 'recovery');
    }

    /**
     * Send notifications to all enabled channels
     */
    protected function sendNotifications(array $message, array $context = [], string $type = 'failure'): void
    {
        $channels = $this->config->get('smart-failover.notifications.channels', []);

        // Send Slack notification
        if ($channels['slack']['enabled'] ?? false) {
            $this->sendSlackNotification($message, $context, $type);
        }

        // Send Telegram notification
        if ($channels['telegram']['enabled'] ?? false) {
            $this->sendTelegramNotification($message, $context, $type);
        }

        // Send email notification
        if ($channels['email']['enabled'] ?? false) {
            $this->sendEmailNotification($message, $context, $type);
        }
    }

    /**
     * Send Slack notification
     */
    protected function sendSlackNotification(array $message, array $context = [], string $type = 'failure'): void
    {
        try {
            $slackConfig = $this->config->get('smart-failover.notifications.channels.slack');
            $webhookUrl = $slackConfig['webhook_url'] ?? null;

            if (!$webhookUrl) {
                $this->logger->warning('Slack webhook URL not configured');
                return;
            }

            $color = $type === 'failure' ? 'danger' : 'good';
            $emoji = $type === 'failure' ? ':warning:' : ':white_check_mark:';

            $payload = [
                'channel' => $slackConfig['channel'] ?? '#alerts',
                'username' => $slackConfig['username'] ?? 'SmartFailover',
                'icon_emoji' => $emoji,
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => $message['title'],
                        'text' => $message['description'],
                        'fields' => [
                            [
                                'title' => 'Service',
                                'value' => $message['service'] ?? 'Unknown',
                                'short' => true,
                            ],
                            [
                                'title' => 'Timestamp',
                                'value' => $message['timestamp'],
                                'short' => true,
                            ],
                        ],
                        'footer' => 'SmartFailover',
                        'ts' => time(),
                    ],
                ],
            ];

            Http::timeout(10)->post($webhookUrl, $payload);

            $this->logger->info('Slack notification sent', [
                'type' => $type,
                'channel' => $slackConfig['channel'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Telegram notification
     */
    protected function sendTelegramNotification(array $message, array $context = [], string $type = 'failure'): void
    {
        try {
            $telegramConfig = $this->config->get('smart-failover.notifications.channels.telegram');
            $botToken = $telegramConfig['bot_token'] ?? null;
            $chatId = $telegramConfig['chat_id'] ?? null;

            if (!$botToken || !$chatId) {
                $this->logger->warning('Telegram bot token or chat ID not configured');
                return;
            }

            $emoji = $type === 'failure' ? '⚠️' : '✅';
            $text = "{$emoji} *{$message['title']}*\n\n";
            $text .= "{$message['description']}\n\n";
            $text .= "*Service:* {$message['service']}\n";
            $text .= "*Time:* {$message['timestamp']}";

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            
            Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);

            $this->logger->info('Telegram notification sent', [
                'type' => $type,
                'chat_id' => $chatId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Telegram notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(array $message, array $context = [], string $type = 'failure'): void
    {
        try {
            $emailConfig = $this->config->get('smart-failover.notifications.channels.email');
            $to = $emailConfig['to'] ?? null;
            $from = $emailConfig['from'] ?? 'noreply@example.com';

            if (!$to) {
                $this->logger->warning('Email notification recipient not configured');
                return;
            }

            $subject = $type === 'failure' 
                ? "SmartFailover Alert: {$message['title']}"
                : "SmartFailover Recovery: {$message['title']}";

            Mail::raw($message['description'], function ($mail) use ($to, $from, $subject) {
                $mail->to($to)
                     ->from($from)
                     ->subject($subject);
            });

            $this->logger->info('Email notification sent', [
                'type' => $type,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format failure message
     */
    protected function formatFailureMessage(\Exception $exception, array $context = []): array
    {
        return [
            'title' => 'Service Failure Detected',
            'description' => "A service failure has been detected:\n\n" .
                           "Error: {$exception->getMessage()}\n" .
                           "File: {$exception->getFile()}:{$exception->getLine()}\n" .
                           (!empty($context) ? "Context: " . json_encode($context, JSON_PRETTY_PRINT) : ''),
            'service' => $context['service'] ?? 'Unknown',
            'timestamp' => now()->toISOString(),
            'severity' => 'high',
        ];
    }

    /**
     * Format recovery message
     */
    protected function formatRecoveryMessage(string $service, array $context = []): array
    {
        return [
            'title' => 'Service Recovery',
            'description' => "Service has recovered and is now operational:\n\n" .
                           "Service: {$service}\n" .
                           (!empty($context) ? "Details: " . json_encode($context, JSON_PRETTY_PRINT) : ''),
            'service' => $service,
            'timestamp' => now()->toISOString(),
            'severity' => 'info',
        ];
    }

    /**
     * Get throttle key for exception
     */
    protected function getThrottleKey(\Exception $exception): string
    {
        return 'smart_failover_throttle_' . md5($exception->getMessage() . $exception->getFile() . $exception->getLine());
    }

    /**
     * Check if notification is throttled
     */
    protected function isThrottled(string $throttleKey): bool
    {
        if (!$this->config->get('smart-failover.notifications.throttle.enabled', true)) {
            return false;
        }

        return Cache::has($throttleKey);
    }

    /**
     * Set throttle for notification
     */
    protected function setThrottle(string $throttleKey): void
    {
        if (!$this->config->get('smart-failover.notifications.throttle.enabled', true)) {
            return;
        }

        $minutes = $this->config->get('smart-failover.notifications.throttle.minutes', 15);
        Cache::put($throttleKey, true, now()->addMinutes($minutes));
    }
}