<?php

namespace Binlog\Collector\Monitor\Constants;

class TimeMonitorConst
{
    public const TYPE_BINLOG_WORKER = 'binlog_collect_worker';
    public const TYPE_BINLOG_PARTITIONER = 'binlog_collect_partitioner';
    public const TYPE_ONCE_BINLOG_WORKER = 'once_binlog_collect_worker';
    public const TYPE_ONCE_BINLOG_PARTITIONER = 'once_binlog_collect_partitioner';
}
