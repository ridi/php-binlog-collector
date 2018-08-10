<?php

namespace Binlog\Collector\Dto;

use MySQLReplication\Definitions\ConstEventsNames;

/**
 * Class BinlogHistoryDto
 * @package Binlog\Collector\Dto
 */
class BinlogHistoryDto
{
    /** @var int - bigint */
    public $id;
    /** @var string */
    public $gtid;
    /** @var string */
    public $reg_date;
    /** @var string */
    public $table_name;
    /** @var string */
    public $pk_ids;
    /** @var string */
    public $action;
    /** @var string */
    public $column;
    /** @var mixed */
    private $data_dict;
    /** @var int */
    public $event_index;
    /** @var OnlyBinlogOffsetDto */
    public $binlog_offset_dto;
    /** @var int */
    public $binlog_id;
    /** @var int */
    public $row_id;

    /**
     * @param OnlyBinlogOffsetDto $binlog_offset_dto
     * @param string              $table_name
     * @param string              $pk_ids
     * @param string              $action
     * @param string              $reg_date
     * @param array               $data_dict
     *
     * @param int                 $event_index
     *
     * @return BinlogHistoryDto
     */
    public static function importFromLog(
        OnlyBinlogOffsetDto $binlog_offset_dto,
        string $table_name,
        string $pk_ids,
        string $action,
        string $reg_date,
        array $data_dict,
        int $event_index
    ): self {
        $dto = new self;
        $dto->gtid = $binlog_offset_dto->getBinlogKey();
        $dto->binlog_offset_dto = $binlog_offset_dto;
        $dto->table_name = $table_name;
        $dto->pk_ids = $pk_ids;
        $dto->action = $action;
        $dto->data_dict = $data_dict;
        $dto->reg_date = $reg_date;
        $dto->event_index = $event_index;

        return $dto;
    }

    public function getGtid(): string
    {
        return 'bin|' . $this->gtid . '|' . $this->event_index;
    }

    public function exportBinlogInfoDatabaseVer3(): array
    {
        $dict = [
            'binlog_filename' => $this->binlog_offset_dto->file_name,
            'gtid_end_pos' => $this->binlog_offset_dto->position,
            'reg_date' =>$this->reg_date,
        ];
        if ($this->binlog_id !== null) {
            $dict['id'] = $this->binlog_id;
        }

        return $dict;
    }

    public function findBinlogIdKeyVer3(): string
    {
        return $this->binlog_offset_dto->file_name . $this->binlog_offset_dto->position . $this->reg_date;
    }

    public function exportRowInfoDatabaseVer3(): array
    {
        $dict = [
            'binlog_id' => $this->binlog_id,
            'idx' => $this->event_index,
            'table_name' => $this->table_name,
            'pk_ids' => $this->pk_ids,
            'action' => $this->action,
            'reg_date' => $this->reg_date,
        ];
        if ($this->row_id !== null) {
            $dict['id'] = $this->row_id;
        }

        return $dict;
    }

    public function findRowIdKeyVer3(): string
    {
        return $this->binlog_id . $this->event_index . $this->table_name . $this->pk_ids;
    }

    public function exportColumnInfoWithRowIdDatabaseVer3(): array
    {
        $dtos = [];
        if ($this->action === ConstEventsNames::WRITE) {
            foreach ($this->data_dict as $key => $value) {
                $dtos[] = [
                    'row_id' => $this->row_id,
                    'column' => $key,
                    'data_before' => null,
                    'data_after' => $value,
                ];
            }
        } elseif ($this->action === ConstEventsNames::UPDATE) {
            $before_dict = $this->data_dict['before'];
            $after_dict = $this->data_dict['after'];
            if (is_array($after_dict)) {
                foreach ($after_dict as $key => $value) {
                    $dtos[] = [
                        'row_id' => $this->row_id,
                        'column' => $key,
                        'data_before' => $before_dict[$key],
                        'data_after' => $value,
                    ];
                }
            }
        } elseif ($this->action === ConstEventsNames::DELETE) {
            foreach ($this->data_dict as $key => $value) {
                $dtos[] = [
                    'row_id' => $this->row_id,
                    'column' => $key,
                    'data_before' => $value,
                    'data_after' => null,
                ];
            }
        }

        return $dtos;
    }
}
