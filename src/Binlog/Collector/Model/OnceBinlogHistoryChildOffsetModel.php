<?php

namespace Binlog\Collector\Model;

use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Dto\OnlyGtidOffsetRangeDto;

class OnceBinlogHistoryChildOffsetModel extends BinlogHistoryBaseModel
{
    /**
     * @return OnlyGtidOffsetRangeDto[]
     */
    public function getChildGtidOffsetRanges(): array
    {
        $dicts = $this->db->sqlDicts(
            'SELECT * from platform_once_history_child_offset order by child_index'
        );

        $dtos = [];
        foreach ($dicts as $dict) {
            $dtos[] = OnlyGtidOffsetRangeDto::importFromDict($dict);
        }

        return $dtos;
    }

    public function getChildGtidOffsetRangeCount(): int
    {
        return $this->db->sqlData('SELECT count(*) from platform_once_history_child_offset');
    }

    public function upsertChildGtidOffsetRange(
        int $child_index,
        OnlyBinlogOffsetDto $current_binlog_offset_dto,
        OnlyBinlogOffsetDto $end_gtid_offset_dto,
        string $current_binlog_offset_date
    ): int {
        $array = [
            'child_index' => $child_index,
            'current_bin_log_file_name' => $current_binlog_offset_dto->file_name,
            'current_bin_log_position' => $current_binlog_offset_dto->position,
            'end_bin_log_file_name' => $end_gtid_offset_dto->file_name,
            'end_bin_log_position' => $end_gtid_offset_dto->position,
            'current_bin_log_position_date' => $current_binlog_offset_date,
        ];

        return $this->db->sqlInsertOrUpdate('platform_once_history_child_offset', $array);
    }

    public function insertChildGtidOffsetRange(OnlyGtidOffsetRangeDto $gtid_offset_range_dto): int
    {
        return $this->db->sqlInsert(
            'platform_once_history_child_offset',
            $gtid_offset_range_dto->exportDatabase()
        );
    }

    public function deleteAllChildGtidOffsetRanges(): int
    {
        $where = [
            'child_index' => sqlNot(''),
        ];

        return $this->db->sqlDelete('platform_once_history_child_offset', $where);
    }

    public function deleteChildGtidOffsetRangeById(int $child_index): int
    {
        $where = [
            'child_index' => $child_index,
        ];

        return $this->db->sqlDelete('platform_once_history_child_offset', $where);
    }

    public function getMinCurrentBinlogPositionDate(): ?string
    {
        return $this->db->sqlData('SELECT MIN(current_bin_log_position_date) FROM platform_once_history_child_offset');
    }
}
