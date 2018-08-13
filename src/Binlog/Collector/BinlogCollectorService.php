<?php

namespace Binlog\Collector;

use Binlog\Collector\Exception\MsgException;

class BinlogCollectorService
{
    public static function getGuaranteedEventFinishedDate(): string
    {
        $binlog_history_service = new BinlogHistoryService();
        $min_current_binlog_position_date = $binlog_history_service->getMinCurrentBinlogPositionDate();

        if ($min_current_binlog_position_date !== null) {
            return $min_current_binlog_position_date;
        }

        $parent_binlog_date =  $binlog_history_service->getParentBinlogDate();

        if ($parent_binlog_date === null) {
            throw new MsgException('not existed parent_binlog_date');
        }

        return $parent_binlog_date;
    }
}
