<?php

namespace Binlog\Collector;

use Binlog\Collector\Dto\BinlogOffsetDto;
use Binlog\Collector\Model\ReplicationDbModel;
use Binlog\Collector\Utils\BinlogUtils;
use Monolog\Logger;

class BinlogPosFinder
{
    /** @var Logger */
    private $logger;
    /** @var ReplicationDbModel */
    private $replication_db_model;
    /** @var int */
    private $previous_file_count;
    /** @var int */
    private $limit_previous_file_count;

    private const ROW_COUNT = 100000;

    private const NO_SKIP_SERVER_ID = 'none';

    public function __construct(Logger $logger, ReplicationDbModel $replication_db_model)
    {
        $this->logger = $logger;
        $this->replication_db_model = $replication_db_model;
    }

    /**
     * @param BinlogOffsetDto $start_binlog_offset_dto
     *
     * @param string          $target_gtid ex) 0-146-1683252403,12-12-19056634,11-11-1887613118
     * @param string          $skip_server_id
     * @param int             $limit_previous_file_count
     *
     * @return BinlogOffsetDto|null
     */
    public function findBinlogOffsetDto(
        BinlogOffsetDto $start_binlog_offset_dto,
        string $target_gtid,
        string $skip_server_id = self::NO_SKIP_SERVER_ID,
        int $limit_previous_file_count = 5
    ): ?BinlogOffsetDto {
        $target_gtids = explode(',', $target_gtid);
        $this->previous_file_count = 1;
        $this->limit_previous_file_count = $limit_previous_file_count;
        $binlog_file_name = $start_binlog_offset_dto->file_name;
        $dicts = $this->replication_db_model->showBinlogEventsFromInit($binlog_file_name, 0, self::ROW_COUNT);

        $dict_count = count($dicts);
        $previous_pos = 'init';
        $previous_binlog_file_name = $binlog_file_name;
        $gtid = '';
        while ($dict_count > 0) {
            foreach ($dicts as $dict) {
                if ($dict['Event_type'] === 'Gtid') {
                    $gtid_end_log_pos = $dict['End_log_pos'];

                    //BEGIN GTID 0-43-14532884 cid=97361316:
                    //GTID 0-43-14535494:
                    //BEGIN GTID 0-43-14535495:
                    $gtid = explode(' ', trim(str_replace(['BEGIN', 'GTID'], '', $dict['Info'])))[0];

                    if (in_array($gtid, $target_gtids)) {
                        $found_gtid = $this->replication_db_model->getBinlogGtidPos(
                            $binlog_file_name,
                            $gtid_end_log_pos
                        );
                        if ($this->isSameGtidExceptSkipSeverId($target_gtid, $found_gtid, $skip_server_id)) {
                            return BinlogOffsetDto::importBinlogOffset(
                                $found_gtid,
                                $binlog_file_name,
                                $gtid_end_log_pos
                            );
                        }
                    }
                }
            }

            $next_pos = $dicts[$dict_count - 1]['End_log_pos'];
            if ($previous_binlog_file_name !== $binlog_file_name) {
                $previous_pos = 'init';
            }
            $this->logger->info("no finding gtid: {$binlog_file_name} ({$previous_pos} ~ {$next_pos}) [{$gtid}]");

            $previous_pos = $next_pos;
            $previous_binlog_file_name = $binlog_file_name;
            [$dicts, $binlog_file_name] = $this->getNextEventDictsAndPreviousFile($binlog_file_name, $next_pos, true);

            $dict_count = count($dicts);
        }

        return null;
    }

    public function isSameGtidExceptSkipSeverId(string $target_gtid, string $found_gtid, string $skip_server_id): bool
    {
        if ($skip_server_id === self::NO_SKIP_SERVER_ID) {
            return self::sortGtidList($target_gtid) === self::sortGtidList($found_gtid);
        }
        return self::removeSkipServerId($target_gtid, $skip_server_id)
            === self::removeSkipServerId($found_gtid, $skip_server_id);
    }

    public static function sortGtidList(string $gtid_list): string
    {
        $new_gtids = explode(',', $gtid_list);
        sort($new_gtids);

        return implode(',', $new_gtids);
    }

    public static function removeSkipServerId(string $gtid, string $skip_server_id): string
    {
        $target_gtids = explode(',', $gtid);

        $pattern = '/^(?:[0-9]+)?-([0-9]+)-(?:[0-9]+)?$/';

        $new_gtids = [];
        foreach ($target_gtids as $target_gtid) {
            $is_found = (preg_match($pattern, $target_gtid, $matches));
            if ($is_found && $skip_server_id === $matches[1]) {
                continue;
            }
            $new_gtids[] = $target_gtid;
        }
        sort($new_gtids);

        return implode(',', $new_gtids);
    }

    private function getNextEventDictsAndPreviousFile(
        string $binlog_file_name,
        int $pos,
        bool $use_previous_seq_if_failed
    ): array {
        $dicts = $this->replication_db_model->showBinlogEvents($binlog_file_name, $pos, 0, self::ROW_COUNT);
        if (count($dicts) === 0
            && $use_previous_seq_if_failed
            && $this->previous_file_count < $this->limit_previous_file_count
        ) {
            $this->previous_file_count++;
            $binlog_file_name = BinlogUtils::calculatePreviousSeqFile($binlog_file_name);
            $dicts = $this->replication_db_model->showBinlogEventsFromInit($binlog_file_name, 0, self::ROW_COUNT);
        }

        return [$dicts, $binlog_file_name];
    }
}
