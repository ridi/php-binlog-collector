<?php

namespace Binlog\Collector;

use Binlog\Collector\Dto\BinlogHistoryDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\External\RowEventValueSkipperInterface;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\RowsDTO;
use MySQLReplication\Event\RowEvent\ColumnDTO;
use MySQLReplication\Event\RowEvent\ColumnDTOCollection;

class BinlogHistoryCollector
{
    /** @var RowEventValueSkipperInterface */
    private $row_event_value_skipper;

    public function __construct(RowEventValueSkipperInterface $row_event_value_skipper)
    {
        $this->row_event_value_skipper = $row_event_value_skipper;
    }

    /**
     * @param OnlyBinlogOffsetDto $binlog_offset_dto
     * @param EventDTO[]          $events
     *
     * @return BinlogHistoryDto[]
     */
    public function collect(OnlyBinlogOffsetDto $binlog_offset_dto, array $events): array
    {
        $binlog_history_dtos = [];
        foreach ($events as $event_index => $event) {
            if ($event instanceof RowsDTO) {
                $dtos = $this->collectUniversalHistoryDtos($binlog_offset_dto, $event, $event_index);
                $binlog_history_dtos = array_merge($binlog_history_dtos, $dtos);
            }
        }

        return $binlog_history_dtos;
    }

    /**
     * @param OnlyBinlogOffsetDto $binlog_offset_dto
     * @param RowsDTO             $event
     * @param int                 $event_index
     *
     * @return BinlogHistoryDto[]
     */
    private function collectUniversalHistoryDtos(
        OnlyBinlogOffsetDto $binlog_offset_dto,
        RowsDTO $event,
        int $event_index
    ): array {
        $ret_dtos = [];
        $table = $event->getTableMap()->getTable();
        $schema_name = $event->getTableMap()->getDatabase() . '.' . $table;
        $values = $event->getValues();
        $reg_date = $event->getEventInfo()->getDateTime();
        $action = $event->getType();

        foreach ($values as $value) {
            $timestamp = $event->getEventInfo()->getTimestamp();
            if (!$this->row_event_value_skipper->isTargetEventValue($timestamp, $table, $action, $value)) {
                continue;
            }

            if ($action === ConstEventsNames::UPDATE) {
                $pk_ids = $this->findPrimaryValues($event->getTableMap()->getColumnDTOCollection(), $value['before']);
                $value = $this->getOnlyChangedValue($value);
            } else {
                $pk_ids = $this->findPrimaryValues($event->getTableMap()->getColumnDTOCollection(), $value);
            }
            $ret_dtos[] = BinlogHistoryDto::importFromLog(
                $binlog_offset_dto,
                $schema_name,
                $pk_ids,
                $action,
                $reg_date,
                $value,
                $event_index
            );
        }

        return $ret_dtos;
    }

    private function findPrimaryValues(ColumnDTOCollection $column_dto_collection, array $row_values): string
    {
        $pk_ids = [];
        /** @var ColumnDto $column_dto */
        foreach ($column_dto_collection as $column_dto) {
            if ($column_dto->isPrimary()) {
                $pk_ids[] = $row_values[$column_dto->getName()];
            }
        }
        if (empty($pk_ids)) {
            return '?';
        }

        return implode(',', $pk_ids);
    }

    private function getOnlyChangedValue(array $value): array
    {
        $new_value = [];
        $before = $value['before'];
        $after = $value['after'];

        $new_after = [];

        foreach ($before as $key => $val) {
            if ($before[$key] !== $after[$key]) {
                $new_after[$key] = $after[$key];
            }
        }
        $new_value['after'] = $new_after;
        $new_value['before'] = $before;

        return $new_value;
    }
}
