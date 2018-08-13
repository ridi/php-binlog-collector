<?php

namespace Binlog\Collector;

use Binlog\Collector\Interfaces\BinlogHistoryServiceInterface;
use Binlog\Collector\Model\ReplicationDbModel;
use Monolog\Logger;
use MySQLReplication\Config\Config;

class BinlogHistoryGtidChildUpdater
{
    private const CHILD_ONCE_BINLOG_FETCH_LIMIT = 1000;

    /** @var ReplicationQuery */
    private $replication_query;
    /** @var ReplicationDbModel */
    private $replication_db_model;
    /** @var Logger */
    private $logger;
    /** @var int */
    private $remain_binlog_count;
    /** @var BinlogHistoryServiceInterface */
    private $binlog_history_service;


    public function __construct(Logger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->replication_db_model = new ReplicationDbModel($config);
        $this->replication_query = new ReplicationQuery($this->replication_db_model);
        $this->binlog_history_service = new BinlogHistoryService();
    }

    private function getFetchCount(): int
    {
        if ($this->remain_binlog_count <= 0) {
            return 0;
        }
        $fetch_count = self::CHILD_ONCE_BINLOG_FETCH_LIMIT;
        if ($this->remain_binlog_count < self::CHILD_ONCE_BINLOG_FETCH_LIMIT) {
            $fetch_count = $this->remain_binlog_count;
        }
        $this->remain_binlog_count -= $fetch_count;

        return $fetch_count;
    }

    public function execute(int $last_binlog_id, int $max_binlog_count): void
    {
        $this->remain_binlog_count = $max_binlog_count;
        $fetch_count = $this->getFetchCount();
        $is_first = true;
        while ($fetch_count > 0) {
            //최초 $last_binlog_id 포함
            if ($is_first) {
                $dicts = $this->binlog_history_service->getEmptyGtidBinlogDictsByLesserEqualId(
                    $last_binlog_id,
                    $fetch_count
                );
                $is_first = false;
            } else {
                $dicts = $this->binlog_history_service->getEmptyGtidBinlogDictsByLesserId(
                    $last_binlog_id,
                    $fetch_count
                );
            }
            $is_all_updated = $this->calculateGtidAndUpdate($dicts);
            if (!$is_all_updated) {
                break;
            }
            $dict_count = count($dicts);
            $last_binlog_id = $dicts[$dict_count - 1]['id'];
            $fetch_count = $this->getFetchCount();
        }
    }

    private function calculateGtidAndUpdate(array $dicts): bool
    {
        $new_dicts = self::sortDescendingByBinlogFileNameAndGtidEndPos($dicts);

        foreach ($new_dicts as $dict) {
            $id = intval($dict['id']);
            $binlog_file_name = $dict['binlog_filename'];
            $binlog_position = intval($dict['gtid_end_pos']);
            try {
                $binlog_offset_dto = $this->replication_query->convertToBinlogOffsetDto(
                    $binlog_file_name,
                    $binlog_position
                );
                $this->binlog_history_service->updateBinlogGtid($id, $binlog_offset_dto->mariadb_gtid);
            } catch (\Exception $exception) {
                $this->logger->info(
                    "Gtid ({$id}:{$binlog_file_name},{$binlog_position}) " .
                    "변환 실패(오래된 Binlog Offset인 경우 발생 할 수 있습니다.)"
                );

                return false;
            }
        }

        return true;
    }

    public static function sortDescendingByBinlogFileNameAndGtidEndPos(array $dicts): array
    {
        usort(
            $dicts,
            function ($a, $b) {
                $ret_val = $b['binlog_filename'] <=> $a['binlog_filename'];
                if ($ret_val === 0) {
                    return $b['gtid_end_pos'] <=> $a['gtid_end_pos'];
                }

                return $ret_val;
            }
        );

        return $dicts;
    }

    public function close(): void
    {
        $this->replication_db_model->close();
    }
}
