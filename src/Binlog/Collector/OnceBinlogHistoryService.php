<?php

namespace Binlog\Collector;

use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\Dto\BinlogHistoryDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Dto\OnlyGtidOffsetRangeDto;
use Binlog\Collector\Interfaces\BinlogHistoryServiceInterface;
use Binlog\Collector\Model\OnceBinlogHistoryChildOffsetModel;
use Binlog\Collector\Model\OnceBinlogHistoryModel;
use Binlog\Collector\Model\OnceBinlogHistoryParentOffsetModel;

/**
 * Class OnceBinlogHistoryService
 * @package Binlog\Collector
 */
class OnceBinlogHistoryService implements BinlogHistoryServiceInterface
{
    public function getChildSlaveId(int $index): int
    {
        return intval(BinlogEnvConfig::ONCE_CHILD_SLAVE_PREFIX_ID . $index);
    }

    public function getTemporarySlaveId(): int
    {
        return BinlogEnvConfig::ONCE_CHILD_TEMPORARY_SLAVE_ID;
    }

    public function transactional(callable $callable): bool
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->transactional($callable);
    }

    /**
     * @return OnlyGtidOffsetRangeDto[]
     */
    public function getChildGtidOffsetRanges(): array
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->getChildGtidOffsetRanges();
    }

    public function getChildGtidOffsetRangeCount(): int
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->getChildGtidOffsetRangeCount();
    }

    public function upsertChildGtidOffsetRange(
        int $child_index,
        OnlyBinlogOffsetDto $current_binlog_offset_dto,
        OnlyBinlogOffsetDto $end_gtid_offset_dto,
        string $current_binlog_offset_date
    ): int {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        $affected_rows = $child_offset_model->upsertChildGtidOffsetRange(
            $child_index,
            $current_binlog_offset_dto,
            $end_gtid_offset_dto,
            $current_binlog_offset_date
        );

        return $affected_rows;
    }

    public function insertChildGtidOffsetRange(OnlyGtidOffsetRangeDto $gtid_offset_range_dto): int
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->insertChildGtidOffsetRange($gtid_offset_range_dto);
    }

    public function deleteAllChildGtidOffsetRanges(): int
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->deleteAllChildGtidOffsetRanges();
    }

    public function deleteChildGtidOffsetRangeById(int $child_index): int
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->deleteChildGtidOffsetRangeById($child_index);
    }

    /**
     * @return string|null
     */
    public function getMinCurrentBinlogPositionDate()
    {
        $child_offset_model = OnceBinlogHistoryChildOffsetModel::createBinlogHistoryWrite();

        return $child_offset_model->getMinCurrentBinlogPositionDate();
    }

    /**
     * insert Universal History Bulk
     *
     * @param BinlogHistoryDto[] $dtos
     *
     * @return int
     */
    public function insertHistoryBulk(array $dtos): int
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        return $binlog_history_model->insertHistoryBulk($dtos);
    }

    public function getEmptyGtidBinlogCount(): int
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        return $binlog_history_model->getEmptyGtidBinlogCount();
    }

    /**
     * @return int|null
     */
    public function getRecentEmptyGtidBinlogId()
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        return $binlog_history_model->getRecentEmptyGtidBinlogId();
    }

    public function getEmptyGtidBinlogDictsByLesserEqualId(int $id, int $limit): array
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        return $binlog_history_model->getEmptyGtidBinlogDictsByLesserEqualId($id, $limit);
    }

    public function getEmptyGtidBinlogDictsByLesserId(int $id, int $limit): array
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        return $binlog_history_model->getEmptyGtidBinlogDictsByLesserId($id, $limit);
    }

    /**
     * @param int $id
     * @param int $offset
     *
     * @return int|null
     */
    public function getEmptyGtidBinlogIdByLesserIdAndOffset(int $id, int $offset)
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        return $binlog_history_model->getEmptyGtidBinlogIdByLesserIdAndOffset($id, $offset);
    }

    public function updateBinlogGtid(int $id, string $gtid)
    {
        $binlog_history_model = OnceBinlogHistoryModel::createBinlogHistoryWrite();

        $binlog_history_model->updateBinlogGtid($id, $gtid);
    }

    public function getParentBinlogOffset(): OnlyBinlogOffsetDto
    {
        $parent_offset_model = OnceBinlogHistoryParentOffsetModel::createBinlogHistoryWrite();

        return $parent_offset_model->getParentBinlogOffset();
    }

    /**
     * @return string|null
     */
    public function getParentBinlogDate()
    {
        $parent_offset_model = OnceBinlogHistoryParentOffsetModel::createBinlogHistoryWrite();

        return $parent_offset_model->getParentBinlogDate();

    }

    public function upsertParentBinlogOffset(OnlyBinlogOffsetDto $binlog_offset_dto, string $binlog_date = null): int
    {
        $parent_offset_model = OnceBinlogHistoryParentOffsetModel::createBinlogHistoryWrite();

        return $parent_offset_model->upsertParentBinlogOffset($binlog_offset_dto, $binlog_date);
    }
}
