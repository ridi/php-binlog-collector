<?php

namespace Binlog\Collector\External;

use Monolog\Logger;

interface ExceptionHandlerInterface
{
    public function getLogger(): Logger;

    public function triggerException(\Exception $e): bool;

    public function triggerMessage(string $string, array $params = [], array $level_or_options = []): bool;
}
