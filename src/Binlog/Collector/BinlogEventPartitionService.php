<?php

namespace Binlog\Collector;

use Binlog\Collector\Dto\BinlogOffsetDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Model\ReplicationDbModel;
use Binlog\Collector\Utils\BinlogUtils;
use Monolog\Logger;

/**
 * Class BinlogEventPartitionService
 * @package Binlog\Collector
 */
class BinlogEventPartitionService
{
	/** @var Logger */
	private $logger;
	/** @var ReplicationDbModel */
	private $replication_db_model;
	/** @var int */
	private $jump_offset;

	public function __construct(
		Logger $logger,
		ReplicationDbModel $replication_db_model,
		int $jump_offset_for_next_partition
	) {
		$this->logger = $logger;
		$this->replication_db_model = $replication_db_model;
		$this->jump_offset = $jump_offset_for_next_partition;
	}

	/**
	 * Binlog 기준 위치로부터
	 * 입력으로 받은 Jump Offset Row 만큼 이동하여, 첫번째 Event_type:Gtid의 End_log_pos의 위치를 반복적으로 수집한다.
	 *  (단, Binlog 이벤트 row가 없으면, 다음 시퀀스 파일의 처음 Jump Offset 만큼에 대해서도 체크해보고 있으면 계속 수집을 진행한다.)
	 * 참고) 여기서 보장을 하는 것은 Jump Offset Row의 개수와 상관없이 무조건 최소 한개 이후의 GTID를 수집.
	 *
	 * @param int                  $partition_max_count
	 * @param BinlogOffsetDto      $start_binlog_offset_dto
	 * @param BinlogOffsetDto|null $end_binlog_offset_dto (해당 범위를 넘어서면 종료)
	 *
	 * @return OnlyBinlogOffsetDto[]
	 */
	public function calculateGtidOffsetDtos(
		int $partition_max_count,
		BinlogOffsetDto $start_binlog_offset_dto,
		BinlogOffsetDto $end_binlog_offset_dto = null
	): array {
		$this->logger->info("Limit Extra PartitionMaxCount: {$partition_max_count}");
		$dtos = [];
		$current_partition_count = 0;

		list($dicts, $current_binlog_file_name) = $this->getNextJumpEventDictsAndFile(
			$start_binlog_offset_dto->file_name,
			$start_binlog_offset_dto->position,
			true
		);

		$dict_count = count($dicts);
		while ($current_partition_count < $partition_max_count && $dict_count > 0) {
			foreach ($dicts as $dict) {
				if ($dict['Event_type'] === 'Gtid') {
					$gtid_end_pos = $dict['End_log_pos'];
					$current_partition_count++;
					$this->dumpPartitionInfo($current_partition_count, $current_binlog_file_name, $gtid_end_pos);
					$dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset($current_binlog_file_name, $gtid_end_pos);
					$dtos[] = $dto;

					// end 범위를 넣어서면 강제 종료
					if ($end_binlog_offset_dto !== null && $end_binlog_offset_dto->compareTo($dto) <= 0) {
						return $dtos;
					}
					break;
				}
			}

			$next_pos = $dicts[$dict_count - 1]['End_log_pos'];
			list($dicts, $current_binlog_file_name) = $this->getNextJumpEventDictsAndFile(
				$current_binlog_file_name,
				$next_pos,
				true
			);
			$dict_count = count($dicts);
		}

		return $dtos;
	}

	/**
	 * 현재 주어진 binlog 위치로부터, jump offset row 이후, 디폴트 개수(1000개) 만큼 가져옴.
	 * 단 $use_next_seq_file를 사용하고, 데이터가 없으면 다음 시퀀스 파일의 처음 jump offset row만큼 가져옴
	 *
	 * @param string $binlog_file_name
	 * @param int    $pos
	 * @param bool   $use_next_seq_file
	 *
	 * @return array
	 */
	private function getNextJumpEventDictsAndFile(string $binlog_file_name, int $pos, bool $use_next_seq_file): array
	{
		$dicts = $this->replication_db_model->showBinlogEvents($binlog_file_name, $pos, $this->jump_offset);
		if (count($dicts) === 0 && $use_next_seq_file) {
			$binlog_file_name = BinlogUtils::calculateNextSeqFile($binlog_file_name);
			$row_count = $this->jump_offset;
			$dicts = $this->replication_db_model->showBinlogEventsFromInit($binlog_file_name, 0, $row_count);
		}

		return [$dicts, $binlog_file_name];
	}

	/**
	 * @param int                 $current_partition_count
	 * @param OnlyBinlogOffsetDto $start_binlog_offset_dto
	 *
	 * @return OnlyBinlogOffsetDto|null
	 */
	public function calculateLastSecondGtidOffsetDto(
		int $current_partition_count,
		OnlyBinlogOffsetDto $start_binlog_offset_dto
	) {
		list($dicts, $current_binlog_file_name) = $this->getNextEventDictsAndFile(
			$start_binlog_offset_dto->file_name,
			$start_binlog_offset_dto->position,
			true
		);

		$current_gtid_count = 0;
		for ($i = count($dicts) - 1; $i > 0; $i--) {
			$dict = $dicts[$i];
			if ($dict['Event_type'] === 'Gtid') {
				$current_gtid_count++;
				//가장 마지막의 gtid는 계속 처리중일지 몰라서 혹시 몰라서 last second로
				if ($current_gtid_count === 2) {
					$gtid_end_pos = $dict['End_log_pos'];
					$this->dumpPartitionInfo($current_partition_count, $current_binlog_file_name, $gtid_end_pos);

					return OnlyBinlogOffsetDto::importOnlyBinlogOffset($current_binlog_file_name, $gtid_end_pos);
				}
			}
		}

		return null;
	}

	private function getNextEventDictsAndFile(string $binlog_file_name, int $pos, bool $use_next_seq_file): array
	{
		$row_count = $this->jump_offset;
		$dicts = $this->replication_db_model->showBinlogEvents($binlog_file_name, $pos, 0, $row_count);
		if (count($dicts) === 0 && $use_next_seq_file) {
			$binlog_file_name = BinlogUtils::calculateNextSeqFile($binlog_file_name);
			$dicts = $this->replication_db_model->showBinlogEventsFromInit($binlog_file_name, 0, $row_count);
		}

		return [$dicts, $binlog_file_name];
	}

	private function dumpPartitionInfo(int $partition_count, string $binlog_file_name, int $gtid_end_log_pos)
	{
		if ($partition_count % 10 !== 0) {
			return;
		}
		$this->logger->info("~ {$partition_count} PartitionCount({$binlog_file_name}/{$gtid_end_log_pos})");
	}
}
