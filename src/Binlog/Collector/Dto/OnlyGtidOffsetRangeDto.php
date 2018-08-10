<?php

namespace Binlog\Collector\Dto;

/**
 * Class OnlyGtidOffsetRangeDto
 * @package Binlog\Collector\Dto
 */
class OnlyGtidOffsetRangeDto
{
    /** @var int */
    public $child_index;
    /** @var OnlyBinlogOffsetDto */
    public $current_dto;
    /** @var OnlyBinlogOffsetDto */
    public $end_dto;
    /** @var string */
    public $current_date;

    public function exportToCurrentBinlogOffset(): OnlyBinlogOffsetDto
    {
        return OnlyBinlogOffsetDto::importOnlyBinlogOffset(
            $this->current_dto->file_name,
            $this->current_dto->position
        );
    }

    public function exportToEndBinlogOffset(): OnlyBinlogOffsetDto
    {
        return OnlyBinlogOffsetDto::importOnlyBinlogOffset(
            $this->end_dto->file_name,
            $this->end_dto->position
        );
    }

    public function __toString(): string
    {
        return "[{$this->child_index}. " .
            "({$this->current_date}/{$this->current_dto->file_name}/{$this->current_dto->position})~" .
            "({$this->end_dto->file_name}/{$this->end_dto->position})]";
    }

    public function exportDatabase(): array
    {
        return [
            'current_bin_log_file_name' => $this->current_dto->file_name,
            'current_bin_log_position' => $this->current_dto->position,
            'end_bin_log_file_name' => $this->end_dto->file_name,
            'end_bin_log_position' => $this->end_dto->position,
            'current_bin_log_position_date' => $this->current_date,
        ];
    }

    public static function importFromBinlogOffsets(
        OnlyBinlogOffsetDto $current_dto,
        OnlyBinlogOffsetDto $end_dto,
        string $current_date = null
    ): self {
        $dto = new self();

        $dto->current_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset(
            $current_dto->file_name,
            $current_dto->position
        );
        $dto->end_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset(
            $end_dto->file_name,
            $end_dto->position
        );

        $dto->current_date = $current_date;

        return $dto;
    }

    public static function importFromDict(array $dict): self
    {
        $dto = new self();
        if (isset($dict['child_index'])) {
            $dto->child_index = $dict['child_index'];
        }
        $dto->current_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset(
            $dict['current_bin_log_file_name'],
            $dict['current_bin_log_position']
        );
        $dto->end_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset(
            $dict['end_bin_log_file_name'],
            $dict['end_bin_log_position']
        );

        $dto->current_date = $dict['current_bin_log_position_date'];

        return $dto;
    }
}
