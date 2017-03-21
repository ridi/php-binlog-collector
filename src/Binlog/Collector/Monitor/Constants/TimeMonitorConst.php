<?php

namespace Binlog\Collector\Monitor\Constants;

class TimeMonitorConst
{
	const TYPE_BINLOG_WORKER = 'binlog_collect_worker';
	const TYPE_BINLOG_PARTITIONER = 'binlog_collect_partitioner';
	const TYPE_ONCE_BINLOG_WORKER = 'once_binlog_collect_worker';
	const TYPE_ONCE_BINLOG_PARTITIONER = 'once_binlog_collect_partitioner';
}
