<?php

namespace Binlog\Collector;

use Binlog\Collector\Dto\BinlogOffsetDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Exception\MsgException;
use Binlog\Collector\Model\ReplicationDbModel;
use Binlog\Collector\Utils\BinlogUtils;

class ReplicationQuery
{
    /** @var ReplicationDbModel */
    private $replication_db_model;

    public function __construct(ReplicationDbModel $replication_db_model)
    {
        $this->replication_db_model = $replication_db_model;
    }

    /**
     * @param string $file_name
     * @param int    $position
     * @param bool   $use_strict_check
     *
     * @return OnlyBinlogOffsetDto
     * @throws MsgException
     */
    public function convertToOnlyBinlogOffsetDto(
        string $file_name,
        int $position,
        bool $use_strict_check = false
    ): OnlyBinlogOffsetDto {
        if ($use_strict_check) {
            $mariadb_gtid = $this->getMariaDbGtid($file_name, $position);
            if (empty($mariadb_gtid)) {
                $file_name = BinlogUtils::calculateNextSeqFile($file_name);
            }
        }

        return OnlyBinlogOffsetDto::importOnlyBinlogOffset($file_name, $position);
    }

    /**
     * @param string $file_name
     * @param int    $position
     *
     * @return BinlogOffsetDto
     * @throws MsgException
     */
    public function convertToBinlogOffsetDto(string $file_name, int $position): BinlogOffsetDto
    {
        $mariadb_gtid = $this->getMariaDbGtid($file_name, $position);
        if (empty($mariadb_gtid)) {
            throw new MsgException("can't calculate mariaDb_gtid (using SELECT BINLOG_GTID_POS");
        }

        return BinlogOffsetDto::importBinlogOffset($mariadb_gtid, $file_name, $position);
    }

    /**
     * @param string $file_name
     * @param int    $position
     *
     * @return string
     * @throws MsgException
     */
    private function getMariaDbGtid(string $file_name, int $position): string
    {
        if ($this->replication_db_model === null) {
            throw new MsgException('replication_db_model is null');
        }

        return $this->replication_db_model->getBinlogGtidPos($file_name, $position);
    }

    /**
     * @return BinlogOffsetDto
     * @throws MsgException
     */
    public function getMasterBinlogOffset(): BinlogOffsetDto
    {
        $result = $this->replication_db_model->showMasterStatus();
        if (empty($result)) {
            throw new MsgException("can't execute 'show master status'");
        }

        return $this->convertToBinlogOffsetDto($result['File'], $result['Position']);
    }

    public function showBinlogEvents(string $log_name, int $pos, int $offset = 0, int $row_count = 1000): array
    {
        return $this->replication_db_model->showBinlogEvents($log_name, $pos, $offset, $row_count);
    }

    public function showBinlogEventsFromInit(string $log_name, int $offset = 0, int $row_count = 1000): array
    {
        return $this->replication_db_model->showBinlogEventsFromInit($log_name, $offset, $row_count);
    }

    public function getBinlogGtidPos(string $binlog_filename, int $binlog_offset): string
    {
        return $this->replication_db_model->getBinlogGtidPos($binlog_filename, $binlog_offset);
    }

    /**
     * assertCheckAuth
     * @throws MsgException
     */
    public function assertCheckAuth(): void
    {
        $result = $this->replication_db_model->showMasterStatus();
        if (empty($result)) {
            throw new MsgException("can't execute 'show master status'");
        }
        $log_name = $result['File'];
        $this->replication_db_model->showBinlogEventsUsingThrowException($log_name);
    }

    /**
     * @param string[] $target_table_schemas
     * @param string[] $target_table_names
     *
     * @throws MsgException
     */
    public function assertSelectTables(array $target_table_schemas, array $target_table_names): void
    {
        $table_names = $this->replication_db_model->getTableNames($target_table_schemas);
        $tables_names = collect($table_names)->pluck('TABLE_NAME')->all();

        $diff_tables = array_diff($target_table_names, $tables_names);

        if (count($diff_tables) > 0) {
            throw new MsgException("can't find: " . implode(',', $diff_tables) . ' in information_schema.COLUMNS');
        }
    }
}
