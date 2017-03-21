<?php

namespace Binlog\Collector\Monitor;

use Binlog\Collector\Monitor\Dto\TimeMonitorDto;
use Binlog\Collector\Monitor\Model\BinlogTimeMonitorModel;

class TimeMonitorService
{
	public static function insertTimeMonitor(string $type, int $elapsed_time): int
	{
		$monitor_dto = TimeMonitorDto::importFromElapsedTime($type, $elapsed_time);

		return BinlogTimeMonitorModel::createBinlogHistoryWrite()->insertTimeMonitor($monitor_dto);
	}

	/**
	 * @param int $monitor_id
	 *
	 * @return TimeMonitorDto|null
	 */
	public static function getTimeMonitor(int $monitor_id)
	{
		return BinlogTimeMonitorModel::createBinlogHistoryWrite()->getTimeMonitor($monitor_id);
	}

	public static function deleteTimeMonitor(int $id): int
	{
		return BinlogTimeMonitorModel::createBinlogHistoryWrite()->deleteTimeMonitor($id);
	}

	/**
	 * @param string $type {@link TimeMonitorConst}
	 *
	 * @return string|null
	 */
	public static function getLastTimeMonitor(string $type)
	{
		return BinlogTimeMonitorModel::createBinlogHistoryWrite()->getLastTimeMonitor($type);
	}
}
