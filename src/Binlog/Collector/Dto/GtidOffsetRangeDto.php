<?php

namespace Binlog\Collector\Dto;

use Binlog\Collector\ReplicationQuery;

/**
 * Class GtidOffsetRangeDto
 * @package Binlog\Collector\Dto
 */
class GtidOffsetRangeDto
{
    /** @var int */
    public $child_index;
    /** @var BinLogOffsetDto */
    public $start_dto;
    /** @var BinLogOffsetDto */
    public $end_dto;

    public static function create(
        ReplicationQuery $replication_query,
        int $child_index,
        OnlyGtidOffsetRangeDto $gtid_range_dto
    ): self {
        $start_gtid_offset_dto = $replication_query->convertToBinlogOffsetDto(
            $gtid_range_dto->current_dto->file_name,
            $gtid_range_dto->current_dto->position
        );
        $end_gtid_offset_dto = $replication_query->convertToBinlogOffsetDto(
            $gtid_range_dto->end_dto->file_name,
            $gtid_range_dto->end_dto->position
        );

        return self::importFromInit($child_index, $start_gtid_offset_dto, $end_gtid_offset_dto);
    }

    private static function importFromInit(int $child_index, BinLogOffsetDto $start_dto, BinLogOffsetDto $end_dto): self
    {
        $dto = new self();
        $dto->child_index = $child_index;
        $dto->start_dto = $start_dto;
        $dto->end_dto = $end_dto;

        return $dto;
    }
}
