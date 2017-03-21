<?php

namespace Binlog\Collector\Interfaces;

use Binlog\Collector\Dto\BinlogHistoryDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Dto\OnlyGtidOffsetRangeDto;

/**
 * interface BinlogHistoryServiceInterface
 * @package Binlog\Collector\Interfaces
 */
interface BinlogHistoryServiceInterface
{
	public function getChildSlaveId(int $index): int;

	public function getTemporarySlaveId(): int;

	public function transactional(callable $callable): bool;

	/**
	 * @return OnlyGtidOffsetRangeDto[]
	 */
	public function getChildGtidOffsetRanges(): array;

	public function getChildGtidOffsetRangeCount(): int;

	public function upsertChildGtidOffsetRange(
		int $child_index,
		OnlyBinlogOffsetDto $current_binlog_offset_dto,
		OnlyBinlogOffsetDto $end_gtid_offset_dto,
		string $current_binlog_offset_date
	): int;

	public function insertChildGtidOffsetRange(OnlyGtidOffsetRangeDto $gtid_offset_range_dto): int;

	public function deleteAllChildGtidOffsetRanges(): int;

	public function deleteChildGtidOffsetRangeById(int $child_index): int;

	/**
	 * @return string|null
	 */
	public function getMinCurrentBinlogPositionDate();

	/**
	 * insert Universal History Bulk
	 *
	 * @param BinlogHistoryDto[] $dtos
	 *
	 * @return int
	 */
	public function insertHistoryBulk(array $dtos): int;

	public function getEmptyGtidBinlogCount(): int;

	/**
	 * @return int|null
	 */
	public function getRecentEmptyGtidBinlogId();

	public function getEmptyGtidBinlogDictsByLesserEqualId(int $id, int $limit): array;

	public function getEmptyGtidBinlogDictsByLesserId(int $id, int $limit): array;

	/**
	 * @param int $id
	 * @param int $offset
	 *
	 * @return int|null
	 */
	public function getEmptyGtidBinlogIdByLesserIdAndOffset(int $id, int $offset);

	public function updateBinlogGtid(int $id, string $gtid);

	public function getParentBinlogOffset(): OnlyBinlogOffsetDto;

	/**
	 * @return string|null
	 */
	public function getParentBinlogDate();

	public function upsertParentBinlogOffset(OnlyBinlogOffsetDto $binlog_offset_dto, string $binlog_date = null): int;
}
