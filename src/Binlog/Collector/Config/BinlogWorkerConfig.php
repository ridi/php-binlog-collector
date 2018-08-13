<?php

namespace Binlog\Collector\Config;

use Binlog\Collector\Exception\MsgException;
use MySQLReplication\Config\Config;
use MySQLReplication\Config\ConfigFactory;

class BinlogWorkerConfig
{
    /** @var Config */
    public $connect_config;
    /** @var int */
    public $child_index;
    /** @var bool */
    public $is_all_print_event;
    /** @var int */
    public $child_process_max_count;
    /** @var int */
    public $once_processed_max_event_count_in_gtid;
    /** @var int */
    public $gtid_count_for_persist_per_partition;

    private static function importFromInit(Config $connect_config, array $array): self
    {
        $binlog_config = new self();
        $binlog_config->connect_config = $connect_config;
        $binlog_config->child_index = intval($array['child_index']);
        $binlog_config->is_all_print_event = boolval($array['is_all_print_event']);
        $binlog_config->child_process_max_count = intval($array['child_process_max_count']);
        $binlog_config->once_processed_max_event_count_in_gtid
            = intval($array['once_processed_max_event_count_in_gtid']);
        $binlog_config->gtid_count_for_persist_per_partition = intval($array['gtid_count_for_persist_per_partition']);

        $binlog_config->validate();

        return $binlog_config;
    }

    public static function create(array $binlog_connect_array, array $binlog_config_array): self
    {
        $connect_config = ConfigFactory::makeConfigFromArray($binlog_connect_array);

        return self::importFromInit($connect_config, $binlog_config_array);
    }

    public function validate(): void
    {
        if ($this->child_process_max_count === 0) {
            throw new MsgException('child_process_max_count is empty');
        }
        if ($this->once_processed_max_event_count_in_gtid === 0) {
            throw new MsgException('once_processed_max_event_count_in_gtid is empty');
        }
        if ($this->gtid_count_for_persist_per_partition === 0) {
            throw new MsgException('gtid_count_for_persist_per_partition is empty');
        }
    }
}
