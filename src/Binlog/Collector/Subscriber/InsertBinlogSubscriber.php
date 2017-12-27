<?php

namespace Binlog\Collector\Subscriber;

use Binlog\Collector\Interfaces\BinlogHistoryServiceInterface;
use Monolog\Logger;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\MariaDbGtidLogDTO;
use MySQLReplication\Event\DTO\RotateDTO;
use MySQLReplication\Event\DTO\XidDTO;
use MySQLReplication\Event\EventSubscribers;
use Binlog\Collector\BinlogEventCollector;
use Binlog\Collector\BinlogHistoryCollector;
use Binlog\Collector\Config\BinlogWorkerConfig;
use Binlog\Collector\External\RowEventValueSkipperInterface;
use Binlog\Collector\ReplicationQuery;
use Binlog\Collector\Dto\GtidOffsetRangeDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Exception\BinlogFinishedException;

/**
 * Class InsertBinlogSubscriber
 * @package Binlog\Collector\Subscriber
 */
class InsertBinlogSubscriber extends EventSubscribers
{
    /** @var Logger */
    private $logger;
    /** @var BinlogWorkerConfig */
    private $binlog_worker_config;
    /** @var BinlogEventCollector */
    private $event_collector;
    /** @var BinlogHistoryCollector */
    private $binlog_history_collector;
    /** @var BinlogHistoryServiceInterface */
    private $binlog_history_service;

    public function __construct(
        Logger $logger,
        ReplicationQuery $replication_query,
        BinlogHistoryServiceInterface $binlog_history_service_interface,
        RowEventValueSkipperInterface $row_event_value_skipper,
        BinlogWorkerConfig $binlog_worker_config,
        GtidOffsetRangeDto $child_gtid_offset_range_dto
    ) {
        $this->logger = $logger;
        $this->binlog_worker_config = $binlog_worker_config;
        $this->event_collector = new BinlogEventCollector(
            $binlog_worker_config,
            $replication_query,
            $child_gtid_offset_range_dto
        );
        $this->binlog_history_collector = new BinlogHistoryCollector($row_event_value_skipper);
        $this->binlog_history_service = $binlog_history_service_interface;
    }

    protected function allEvents(EventDTO $event)
    {
        if ($this->binlog_worker_config->is_all_print_event) {
            echo $event;
        }

        if ($this->event_collector->isIgnoreEvent($event)) {
            return;
        }

        // (MariaDbGtidLogDTO)(TableMapDTO)*(DeleteRowsDTO|UpdateRowsDTO|WriteRowsDTO)*(XidDTO)
        switch ($event->getType()) {
            case ConstEventsNames::ROTATE:
                /** @var $event RotateDTO */
                $this->event_collector->setCurrentBinlogFileName($event->getNextBinlog());

                return;
            case ConstEventsNames::MARIADB_GTID:
                /** @var $event MariaDbGtidLogDTO */
                $this->event_collector->updateCurrentGtidOffset($event->getEventInfo()->getPos());
                $binlog_file_name = $this->event_collector->getCurrentGtidOffsetDto()->file_name;
                $this->event_collector->setCurrentBinlogFileName($binlog_file_name);

                return;
        }

        $this->event_collector->assertHasCurrentGtidOffset();
        $this->event_collector->addEvent($event);

        if ($event instanceof XidDTO) {
            $this->processEvents();

            if ($this->event_collector->isGtidCountForPersist()) {
                $this->binlog_history_service->upsertChildGtidOffsetRange(
                    $this->binlog_worker_config->child_index,
                    $this->event_collector->getCurrentGtidOffsetDto(),
                    $this->event_collector->getEndGtidOffsetDto(),
                    $event->getEventInfo()->getDateTime()
                );
            }

            if ($this->event_collector->isCurrentGreaterEqualsThanEndGtidOffset()) {
                $this->binlog_history_service->deleteChildGtidOffsetRangeById(
                    $this->binlog_worker_config->child_index
                );

                $child_gtid_offset_range_dto = $this->event_collector->getChildGtidOffsetRangeDto();

                $processed_event_count = $this->event_collector->getProcessedEventCount();
                $processed_row_count = $this->event_collector->getProcessedRowCount();
                $processed_gtid_count = $this->event_collector->getGtidCount();

                throw new BinlogFinishedException(
                    "child_index({$child_gtid_offset_range_dto->child_index}): ".
                    "process finished".
                    "({$child_gtid_offset_range_dto->start_dto}~{$child_gtid_offset_range_dto->end_dto})" .
                    ", processed({$this->event_collector->getElapsed()}s, gtidCount({$processed_gtid_count}), " .
                    "eventCount({$processed_event_count}), rowCount({$processed_row_count})"
                );
            }
            $this->event_collector->initCurrentGtidOffsetDto();
        } elseif ($this->event_collector->isMoreThanOnceProcessedMaxEventCount()) {
            $this->processEvents();
        }
    }

    private function processEvents()
    {
        $binlog_offset_dto = $this->event_collector->getCurrentGtidOffsetDto();
        $events = $this->event_collector->getEvents();

        $event_count = count($events);
        if ($event_count === 0) {
            return;
        }
        $binlog_history_dtos = $this->binlog_history_collector->collect($binlog_offset_dto, $events);

        if($this->binlog_worker_config->is_all_print_event) {
            $this->printEventsInfo(
                $this->binlog_worker_config->child_index,
                $binlog_offset_dto,
                $event_count,
                count($binlog_history_dtos),
                $events[0]
            );
        }

        $this->event_collector->increaseProcessedEventCount($event_count);
        $this->event_collector->increaseProcessedRowCount(count($binlog_history_dtos));

        $this->binlog_history_service->insertHistoryBulk($binlog_history_dtos);
        $this->event_collector->initEvents();
    }

    private function printEventsInfo(
        int $child_index,
        OnlyBinlogOffsetDto $binlog_offset_dto,
        int $event_count,
        int $universal_history_count,
        EventDto $first_event_dto
    ) {
        $event_count = sprintf("%03d", $event_count);
        $reg_date = $first_event_dto->getEventInfo()->getDateTime();
        $this->logger->info(
            "child_index({$child_index}): processEvent({$event_count}), " .
            "TargetRow({$universal_history_count}): {$binlog_offset_dto->getBinlogKey()} : {$reg_date}"
        );
    }
}
