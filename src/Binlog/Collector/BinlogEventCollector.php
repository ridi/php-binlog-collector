<?php

namespace Binlog\Collector;

use Binlog\Collector\Config\BinlogWorkerConfig;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use Binlog\Collector\Dto\BinlogOffsetDto;
use Binlog\Collector\Dto\GtidOffsetRangeDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Exception\MsgException;

/**
 * Class BinlogEventCollector
 * @package Binlog\Collector
 */
class BinlogEventCollector
{
	/** @var BinlogWorkerConfig */
	private $binlog_worker_config;
	/** @var EventDTO[] */
	private $events = [];
	/** @var int */
	private $gtid_count;
	/** @var int */
	private $start_time;

	/** @var ReplicationQuery */
	private $replication_query;

	/** @var bool */
	private $is_first_start_gtid;
	/** @var OnlyBinlogOffsetDto */
	private $current_gtid_offset_dto;

	/** @var GtidOffsetRangeDto */
	private $child_gtid_offset_range_dto;
	/** $var string */
	private $current_binlog_file_name;

	/** @var int */
	private $processed_event_count;
	/** @var int */
	private $processed_row_count;

	public function __construct(
		BinlogWorkerConfig $binlog_worker_config,
		ReplicationQuery $replication_query,
		GtidOffsetRangeDto $child_gtid_offset_range_dto
	) {
		$this->binlog_worker_config = $binlog_worker_config;
		$this->gtid_count = 0;
		$this->processed_event_count = 0;
		$this->processed_row_count = 0;
		$this->start_time = time();
		$this->is_first_start_gtid = true;

		$this->child_gtid_offset_range_dto = $child_gtid_offset_range_dto;
		$this->current_binlog_file_name = $child_gtid_offset_range_dto->start_dto->file_name;
		$this->replication_query = $replication_query;
	}

	public function initEvents()
	{
		$this->events = [];
	}

	public function addEvent(EventDTO $event)
	{
		if (in_array(
			$event->getType(),
			[
				ConstEventsNames::DELETE,
				ConstEventsNames::UPDATE,
				ConstEventsNames::WRITE,
			]
		)) {
			$this->events[] = $event;
		}
	}

	public function isIgnoreEvent(EventDTO $event): bool
	{
		if (in_array(
			$event->getType(),
			[
				ConstEventsNames::GTID,
				ConstEventsNames::TABLE_MAP,
				ConstEventsNames::QUERY
			]
		)) {
			return true;
		}

		return false;
	}

	/**
	 * @return EventDTO[]
	 */
	public function getEvents(): array
	{
		return $this->events;
	}

	public function increaseGtidCount()
	{
		$this->gtid_count++;
	}

	public function increaseProcessedEventCount(int $count)
	{
		$this->processed_event_count += $count;
	}

	public function increaseProcessedRowCount(int $count)
	{
		$this->processed_row_count += $count;
	}

	/**
	 * @return int
	 */
	public function getProcessedEventCount(): int
	{
		return $this->processed_event_count;
	}

	/**
	 * @return int
	 */
	public function getProcessedRowCount(): int
	{
		return $this->processed_row_count;
	}


	/**
	 * @return int
	 */
	public function getGtidCount(): int
	{
		return $this->gtid_count;
	}

	public function getElapsed(): int
	{
		return (time() - $this->start_time);
	}

	public function updateCurrentGtidOffset(int $gtid_pos)
	{
		$this->increaseGtidCount();

		// serverId를 모르기 때문에 (binLogFileName, binLogPosition)를 통해 GTID 계산 But 오버헤드가 크기 때문에 계산안함 (대략 1초에 70건)
		// 다만 최초 GTID일 때만 계산.
		// 시작 GTID를 (ex:11-11-111)를 세팅하고 분석을 돌리면
		// 최초 GTID는 (ex:11-11-112)인데, 그 사이에 file rotate 이벤트까 끼여 있을 수 있는데,
		// 중간에 파일 바뀐 정보를 추적 못함. 그래서 최초 gtid 변환해보고 없으면 다음 Seq 파일 사용

		if ($this->is_first_start_gtid) {
			$use_strict_check = true;
		} else {
			$use_strict_check = false;
		}

		$this->current_gtid_offset_dto =
			$this->replication_query->convertToOnlyBinlogOffsetDto(
				$this->current_binlog_file_name,
				$gtid_pos,
				$use_strict_check
			);

		if ($this->is_first_start_gtid) {
			$this->is_first_start_gtid = false;
		}
	}

	/**
	 * @throws MsgException
	 */
	public function assertHasCurrentGtidOffset()
	{
		if ($this->current_gtid_offset_dto === null) {
			throw new MsgException('abnormal state: not has current gtid offset(not start mariaDb_gtid_event)');
		}
	}

	public function setCurrentBinlogFileName(string $current_binlog_file_name)
	{
		$this->current_binlog_file_name = $current_binlog_file_name;
	}

	public function initCurrentGtidOffsetDto()
	{
		$this->current_gtid_offset_dto = null;
	}

	public function setStartTime(int $start_time)
	{
		$this->start_time = $start_time;
	}

	public function getEndGtidOffsetDto(): BinlogOffsetDto
	{
		return $this->child_gtid_offset_range_dto->end_dto;
	}

	public function getChildGtidOffsetRangeDto(): GtidOffsetRangeDto
	{
		return $this->child_gtid_offset_range_dto;
	}

	public function getCurrentGtidOffsetDto(): OnlyBinlogOffsetDto
	{
		return $this->current_gtid_offset_dto;
	}

	public function isCurrentGreaterEqualsThanEndGtidOffset(): bool
	{
		$end_gtid_file_name = $this->child_gtid_offset_range_dto->end_dto->file_name;
		$end_gtid_position = $this->child_gtid_offset_range_dto->end_dto->position;
		$current_gtid_file_name = $this->current_gtid_offset_dto->file_name;
		$current_gtid_position = $this->current_gtid_offset_dto->position;
		if ($current_gtid_file_name === $end_gtid_file_name) {
			if ($current_gtid_position >= $end_gtid_position) {
				return true;
			}
		}

		return false;
	}

	public function isMoreThanOnceProcessedMaxEventCount(): bool
	{
		return count($this->events) >= $this->binlog_worker_config->once_processed_max_event_count_in_gtid;
	}

	public function isGtidCountForPersist(): bool
	{
		return ($this->gtid_count % $this->binlog_worker_config->gtid_count_for_persist_per_partition === 0);
	}
}
