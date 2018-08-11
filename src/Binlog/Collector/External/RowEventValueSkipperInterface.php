<?php

namespace Binlog\Collector\External;

interface RowEventValueSkipperInterface
{
    /**
     * @return string[]
     */
    public function getTablesOnly(): array;

    /**
     * @return string[]
     */
    public function getDatabasesOnly(): array;

    public function isTargetEventValue(int $binlog_event_timestamp, string $table, string $type, array $value): bool;
}
