<?php

namespace Binlog\Collector\External;

abstract class AbstractRowEventValueSkipper implements RowEventValueSkipperInterface
{
    /** @var string[] */
    private $target_tables;

    /** @var string[] */
    private $target_databases;

    /**
     * DefaultRowEventValueSkipper constructor.
     *
     * @param string[] $target_tables
     * @param string[] $target_databases
     */
    public function __construct(array $target_tables, array $target_databases)
    {
        $this->target_tables = $target_tables;
        $this->target_databases = $target_databases;
    }

    /**
     * @return string[]
     */
    public function getTablesOnly(): array
    {
        return $this->target_tables;
    }

    /**
     * @return string[]
     */
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
