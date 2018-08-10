<?php

namespace Binlog\Collector\Config;

use Binlog\Collector\Exception\MsgException;
use MySQLReplication\Config\Config;
use MySQLReplication\Config\ConfigFactory;

/**
 * Class BinlogPartitionerConfig
 * @package Binlog\Collector\Config
 */
class BinlogPartitionerConfig
{
    /** @var Config */
    public $connect_config;
    /** @var array TODO Temporary */
    public $binlog_connect_array;

    /** @var int */
    public $gtid_partition_max_count;
    /** @var int */
    public $jump_offset_for_next_partition;

    private static function importFromInit(Config $connect_config, array $array, array $binlog_connect_array): self
    {
        $binlog_config = new self();
        $binlog_config->connect_config = $connect_config;
        $binlog_config->gtid_partition_max_count = intval($array['gtid_partition_max_count']);
        $binlog_config->jump_offset_for_next_partition = intval($array['jump_offset_for_next_partition']);
        $binlog_config->binlog_connect_array = $binlog_connect_array;
        $binlog_config->validate();

        return $binlog_config;
    }

    public static function create(array $binlog_connect_array, array $binlog_config_array): self
    {
        $connect_config = ConfigFactory::makeConfigFromArray($binlog_connect_array);

        return BinlogPartitionerConfig::importFromInit($connect_config, $binlog_config_array, $binlog_connect_array);
    }

    public function validate(): void
    {
        if ($this->gtid_partition_max_count === 0) {
            throw new MsgException('gtid_partition_max_count is empty');
        }
        if ($this->jump_offset_for_next_partition === 0) {
            throw new MsgException('jump_offset_for_next_partition is empty');
        }
    }
}
