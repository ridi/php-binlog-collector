<?php

namespace Binlog\Collector;

class BinlogCollectorService
{
    public static function getGuaranteedEventFinishedDate(): string
    {
        $binlog_history_service = new BinlogHistoryService();
        $min_current_binlog_position_date = $binlog_history_service->getMinCurrentBinlogPositionDate();

        if ($min_current_binlog_position_date !== null) {
            return $min_current_binlog_position_date;
        }

        return $binlog_history_service->getParentBinlogDate();
    }
}
