<?php

namespace Binlog\Collector\External\Impl;

use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\External\ExceptionHandlerInterface;
use Binlog\Collector\External\Sentry\BinlogRavenClient;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Raven_Client;

class DefaultSentryExceptionHandler implements ExceptionHandlerInterface
{
    private const DEFAULT_RAVEN_CLIENT_NAME = '__RAVEN_CLIENT';
    private const LOG_FILE_NAME_EXTENSION = '.log';

    /** @var Logger */
    private $logger;

    /**
     * DefaultSentryExceptionHandler constructor.
     *
     * @param string          $dir
     * @param string          $name
     * @param BinlogEnvConfig $binlog_env_config
     */
    public function __construct(string $dir, string $name, BinlogEnvConfig $binlog_env_config)
    {
        $this->logger = self::createLogger($dir, $name);
        if ($binlog_env_config->enable_sentry) {
            $this->enableSentry($binlog_env_config->sentry_key);
        }

        set_error_handler(
            function ($severity, $message, $file, $line): void {
                if (!(error_reporting() & $severity)) {
                    // This error code is not included in error_reporting
                    return;
                }
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );

        set_exception_handler(
            function (\Throwable $exception): void {
                $this->logger->info('file: ' . $exception->getFile() . "\n");
                $this->logger->info('message: ' . $exception->getMessage() . "\n");
                $this->logger->info('errorCode: ' . $exception->getCode() . "\n");
                $this->logger->info('trace: ' . $exception->getTraceAsString() . "\n");

                if ($exception instanceof \Exception) {
                    self::triggerException($exception);
                } elseif ($exception instanceof \Error) {
                    $error_exception = new \ErrorException(
                        $exception->getMessage(),
                        $exception->getCode(),
                        0,
                        $exception->getFile(),
                        $exception->getLine()
                    );
                    self::triggerException($error_exception);
                }
            }
        );
    }

    /**
     * @param string $dir
     * @param string $name
     *
     * @return Logger
     */
    private static function createLogger(string $dir, string $name): Logger
    {
        $file_name = 'cron_' . $name . self::LOG_FILE_NAME_EXTENSION;

        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler("php://stdout"));
        $logger->pushHandler(new StreamHandler($dir . $file_name));

        return $logger;
    }

    private function enableSentry(string $sentry_key): void
    {
        $raven_client = new BinlogRavenClient($sentry_key, ['processors' => []]);

        $GLOBALS[self::DEFAULT_RAVEN_CLIENT_NAME] = $raven_client;
    }

    public function triggerException(\Exception $e): bool
    {
        if (!$this->hasRavenClientInitialized()) {
            return false;
        }

        $client = $this->getRavenClient();
        if (!($client instanceof Raven_Client)) {
            return false;
        }

        $client->captureException($e);

        return true;
    }

    public function triggerMessage(string $string, array $params = [], array $level_or_options = []): bool
    {
        if (!$this->hasRavenClientInitialized()) {
            return false;
        }

        $client = $this->getRavenClient();
        if (!($client instanceof Raven_Client)) {
            return false;
        }

        $client->captureMessage($string, $params, $level_or_options, true);

        return true;
    }

    private function getRavenClient(): ?Raven_Client
    {
        return $this->hasRavenClientInitialized() ? $GLOBALS[self::DEFAULT_RAVEN_CLIENT_NAME] : null;
    }

    private function hasRavenClientInitialized(): bool
    {
        return isset($GLOBALS[self::DEFAULT_RAVEN_CLIENT_NAME]);
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }
}
