<?php

namespace Binlog\Collector\Monitor;

use Binlog\Collector\Monitor\Dto\TimeMonitorDto;
use Binlog\Collector\Monitor\Model\BinlogTimeMonitorModel;

/**
 * 모니터링 시간을 기록하는 class로, 데이터 조회에 관련된 method는 TimeMonitorService에 작성해주세요.
 *
 * Class TimeMonitor
 * @package Binlog\Collector\Monitor
 */
class TimeMonitor
{
    public static function benchmark(string $type, callable $func)
    {
        $start_time = time();

        $func();

        $elapsed_time = time() - $start_time;
        $monitor_dto = TimeMonitorDto::importFromElapsedTime($type, $elapsed_time);

        BinlogTimeMonitorModel::createBinlogHistoryWrite()->insertTimeMonitor($monitor_dto);
    }
}
