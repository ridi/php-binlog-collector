<?php

namespace Binlog\Collector\External;

/**
 * Class AbstractRowEventValueSkipper
 * @package Binlog\Collector\External
 */
abstract class AbstractRowEventValueSkipper implements RowEventValueSkipperInterface
{
    /**
     * @var string[]
     */
    private $target_tables;

    /** @var string[] */
    private $target_databases;

    /**
     * DefaultRowEventValueSkipper constructor.
     *
     * @param array $target_tables
     * @param array $target_databases
     */
    public function __construct(array $target_tables, array $target_databases)
    {
        $this->target_tables = $target_tables;
        $this->target_databases = $target_databases;
    }

    public function getTablesOnly(): array
    {
        return $this->target_tables;
    }

    public function getDatabasesOnly(): array
    {
        return $this->target_databases;
    }

    abstract public function isTargetEventValue(
        int $binlog_event_timestamp,
        string $table,
        string $type,
        array $value
    ): bool;
}
