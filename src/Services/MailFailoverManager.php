final <?php

namespace Mirzaaghazadeh\SmartFailover\Services;

use Exception;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;

class MailFailoverManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected MailManager $mailManager;
    protected array $mailers = [];
    protected ?string $primaryMailer = null;
    protected array $fallbackMailers = [];
    protected array $healthStatus = [];
    protected int $retryAttempts = 3;
    protected int $retryDelay = 1000; // milliseconds

    public function __construct(Config $config, LoggerInterface $logger, MailManager $mailManager)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->mailManager = $mailManager;
        $this->loadConfiguration();
    }

    /**
     * Set mailers for failover.
     */
    public function setMailers(string $primary, array $fallbacks = []): self
    {
        $this->primaryMailer = $primary;
        $this->fallbackMailers = $fallbacks;
        $this->mailers = array_merge([$primary], $fallbacks);

        return $this;
    }

    /**
     * Send mail with failover protection.
     */
    public function send($mailable, $callback = null): bool
    {
        $mailers = $this->getAvailableMailers();

        foreach ($mailers as $mailerName) {
            try {
                $this->logger->info("Attempting to send mail via {$mailerName}");

                // Switch to the mailer
                $this->switchToMailer($mailerName);

                // Send the mail
                if ($callback && is_callable($callback)) {
                    $result = $callback($this->mailManager->mailer($mailerName));
                } else {
                    Mail::mailer($mailerName)->send($mailable);
                    $result = true;
                }

                $this->markMailerHealthy($mailerName);
                $this->logger->info("Mail sent successfully via {$mailerName}");

                return $result !== false;

            } catch (Exception $e) {
                $this->markMailerUnhealthy($mailerName, $e->getMessage());
                $this->logger->error("Mail failed via {$mailerName}: " . $e->getMessage());

                // Continue to next mailer
                continue;
            }
        }

        $this->logger->error('All mail services failed');
        throw new Exception('All configured mail services are unavailable');
    }

    /**
     * Queue mail with failover protection.
     */
    public function queue($mailable, $queue = null): bool
    {
        $mailers = $this->getAvailableMailers();

        foreach ($mailers as $mailerName) {
            try {
                $this->logger->info("Attempting to queue mail via {$mailerName}");

                // Switch to the mailer
                $this->switchToMailer($mailerName);

                // Queue the mail
                if ($queue) {
                    /** @var \Illuminate\Mail\Mailer $mailer */
                    $mailer = Mail::mailer($mailerName);
                    $mailer->queue($mailable, $queue);
                } else {
                    /** @var \Illuminate\Mail\Mailer $mailer */
                    $mailer = Mail::mailer($mailerName);
                    $mailer->queue($mailable);
                }

                $this->markMailerHealthy($mailerName);
                $this->logger->info("Mail queued successfully via {$mailerName}");

                return true;

            } catch (Exception $e) {
                $this->markMailerUnhealthy($mailerName, $e->getMessage());
                $this->logger->error("Mail queue failed via {$mailerName}: " . $e->getMessage());

                // Continue to next mailer
                continue;
            }
        }

        $this->logger->error('All mail services failed for queuing');
        throw new Exception('All configured mail services are unavailable for queuing');
    }

    /**
     * Check health of all configured mailers.
     */
    public function checkHealth(): array
    {
        $health = [];

        foreach ($this->mailers as $mailerName) {
            $health[$mailerName] = $this->checkMailerHealth($mailerName);
        }

        return $health;
    }

    /**
     * Check health of a specific mailer.
     */
    protected function checkMailerHealth(string $mailerName): array
    {
        $startTime = microtime(true);

        try {
            // Get mailer configuration
            $mailerConfig = $this->config->get("mail.mailers.{$mailerName}");

            if (!$mailerConfig) {
                throw new Exception("Mailer {$mailerName} not configured");
            }

            // Try to get the mailer instance (this will test configuration)
            $mailer = $this->mailManager->mailer($mailerName);

            // For SMTP, we could test the connection
            if ($mailerConfig['transport'] === 'smtp') {
                // Basic configuration validation
                $requiredKeys = ['host', 'port'];
                foreach ($requiredKeys as $key) {
                    if (empty($mailerConfig[$key])) {
                        throw new Exception("Missing required SMTP configuration: {$key}");
                    }
                }
            }

            $responseTime = round(((float) microtime(true) - (float) $startTime) * 1000.0, 2);

            return [
                'status' => 'healthy',
                'response_time' => $responseTime,
                'transport' => $mailerConfig['transport'] ?? 'unknown',
                'last_checked' => now()->toISOString(),
            ];

        } catch (Exception $e) {
            $responseTime = round(((float) microtime(true) - (float) $startTime) * 1000.0, 2);

            return [
                'status' => 'unhealthy',
                'response_time' => $responseTime,
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get available mailers in priority order.
     */
    protected function getAvailableMailers(): array
    {
        $available = [];

        // Add primary mailer if healthy
        if ($this->primaryMailer && $this->isMailerHealthy($this->primaryMailer)) {
            $available[] = $this->primaryMailer;
        }

        // Add healthy fallback mailers
        foreach ($this->fallbackMailers as $mailer) {
            if ($this->isMailerHealthy($mailer)) {
                $available[] = $mailer;
            }
        }

        // If no healthy mailers, try all configured mailers
        if (empty($available)) {
            $available = $this->mailers;
        }

        return $available;
    }

    /**
     * Switch to a specific mailer.
     */
    protected function switchToMailer(string $mailerName): void
    {
        // Set the default mailer
        $this->config->set('mail.default', $mailerName);

        // Purge the mailer instance to force recreation
        $this->mailManager->purge($mailerName);
    }

    /**
     * Check if mailer is healthy.
     */
    protected function isMailerHealthy(string $mailerName): bool
    {
        if (!isset($this->healthStatus[$mailerName])) {
            return true; // Assume healthy if not checked yet
        }

        $status = $this->healthStatus[$mailerName];

        // Consider unhealthy if last failure was recent (within 5 minutes)
        if ($status['status'] === 'unhealthy') {
            $lastFailure = $status['last_failure'] ?? 0;
            return (time() - $lastFailure) > 300; // 5 minutes
        }

        return $status['status'] === 'healthy';
    }

    /**
     * Mark mailer as healthy.
     */
    protected function markMailerHealthy(string $mailerName): void
    {
        $this->healthStatus[$mailerName] = [
            'status' => 'healthy',
            'last_success' => time(),
        ];
    }

    /**
     * Mark mailer as unhealthy.
     */
    protected function markMailerUnhealthy(string $mailerName, string $error): void
    {
        $this->healthStatus[$mailerName] = [
            'status' => 'unhealthy',
            'last_failure' => time(),
            'error' => $error,
        ];
    }

    /**
     * Load configuration.
     */
    protected function loadConfiguration(): void
    {
        $config = $this->config->get('smart-failover.mail', []);

        if (isset($config['primary'])) {
            $this->primaryMailer = $config['primary'];
        }

        if (isset($config['fallbacks'])) {
            $this->fallbackMailers = $config['fallbacks'];
        }

        if (isset($config['retry_attempts'])) {
            $this->retryAttempts = $config['retry_attempts'];
        }

        if (isset($config['retry_delay'])) {
            $this->retryDelay = $config['retry_delay'];
        }

        $this->mailers = array_merge(
            $this->primaryMailer ? [$this->primaryMailer] : [],
            $this->fallbackMailers
        );
    }

    /**
     * Get current mailer status.
     */
    public function getStatus(): array
    {
        return [
            'primary' => $this->primaryMailer,
            'fallbacks' => $this->fallbackMailers,
            'health' => $this->healthStatus,
            'available_mailers' => $this->getAvailableMailers(),
        ];
    }
}
