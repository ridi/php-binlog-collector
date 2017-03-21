<?php

namespace Binlog\Collector\External;

/**
 * interface RowEventValueSkipper
 * @package Binlog\Collector\External
 */
interface RowEventValueSkipperInterface
{
	public function getTablesOnly(): array;
	public function getDatabasesOnly(): array;

	public function isTargetEventValue(
		int $binlog_event_timestamp,
		string $table,
		string $type,
		array $value
	): bool;
}
