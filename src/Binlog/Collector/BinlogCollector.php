<?php

namespace Binlog\Collector;

use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogPartitionerConfig;
use Binlog\Collector\Dto\BinlogOffsetDto;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Dto\OnlyGtidOffsetRangeDto;
use Binlog\Collector\Exception\MsgException;
use Binlog\Collector\Interfaces\BinlogHistoryServiceInterface;
use Binlog\Collector\Model\ReplicationDbModel;
use Binlog\Collector\Subscriber\GetInitBinlogDateSubscriber;
use Monolog\Logger;
use MySQLReplication\MySQLReplicationFactory;

/**
 * Class BinlogCollector
 * @package Binlog\Collector
 */
class BinlogCollector
{
    /** @var ReplicationQuery */
    private $replication_query;
    /** @var BinlogEventPartitionService */
    private $binlog_event_partition_service;
    /** @var array */
    private $binlog_connect_array;
    /** @var Logger */
    private $logger;
    /** @var BinlogHistoryServiceInterface */
    private $binlog_history_service;

    /**
     * BinlogCollector constructor.
     *
     * @param BinlogHistoryServiceInterface $binlog_history_service_interface
     */
    public function __construct(BinlogHistoryServiceInterface $binlog_history_service_interface)
    {
        $this->binlog_history_service = $binlog_history_service_interface;
    }

    /**
     * @param Logger                  $logger
     * @param BinlogPartitionerConfig $partitioner_config
     * @param array                   $argv
     *
     * @throws MsgException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function initialize(Logger $logger, BinlogPartitionerConfig $partitioner_config, array $argv)
    {
        $this->logger = $logger;
        $this->binlog_connect_array = $partitioner_config->binlog_connect_array;
        try {
            $this->assertExecuteCommand($argv);
        } catch (MsgException $e) {
            print('error: ' . $e->getMessage() . "\n");
            $this->printExecuteUsage($argv[0]);
            exit();
        }

        $replication_db_model = new ReplicationDbModel($partitioner_config->connect_config);
        $this->replication_query = new ReplicationQuery($replication_db_model);
        $this->binlog_event_partition_service = new BinlogEventPartitionService(
            $logger,
            $replication_db_model,
            $partitioner_config->jump_offset_for_next_partition
        );
        $master_binlog_offset_dto = $this->replication_query->getMasterBinlogOffset();

        $partition_count = 0;
        switch ($argv[1]) {
            case 'change_pos':
                $partition_count = $this->processChangePosCommand($partitioner_config, $argv);
                break;
            case 'change_range_pos':
                $partition_count = $this->processChangeRangePosCommand($partitioner_config, $argv);
                break;
            case 'continue':
                $partition_count = $this->processContinue($logger, $partitioner_config);
                break;
            case 'child_list':
                $this->printChildGtidOffsetRanges($logger);
                exit;
        }

        $logger->info("Master BinlogOffset: {$master_binlog_offset_dto}");
        $logger->info("Existed PartitionCount: {$partition_count}");
    }

    private function printChildGtidOffsetRanges(Logger $logger): void
    {
        $child_gtid_offset_range_dtos = $this->getChildGtidOffsetRanges();
        foreach ($child_gtid_offset_range_dtos as $i => $child_gtid_offset_range_dto) {
            $logger->info("{$i}. {$child_gtid_offset_range_dto}");
        }
    }

    /**
     * @param BinlogPartitionerConfig $partitioner_config
     * @param array                   $argv
     *
     * @return int
     * @throws MsgException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function processChangePosCommand(BinlogPartitionerConfig $partitioner_config, array $argv): int
    {
        $start_binlog_offset_dto = $this->replication_query->convertToBinlogOffsetDto($argv[2], $argv[3]);
        $gtid_partition_max_count = $partitioner_config->gtid_partition_max_count;

        $new_dtos = $this->calculateChildGtidOffsetRangeDtos($gtid_partition_max_count, $start_binlog_offset_dto);
        $new_dtos_count = count($new_dtos);
        if ($new_dtos_count === 0) {
            throw new MsgException("분석할 데이터가 없습니다. (binlog_offset_range_dtos's count == 0)");
        }

        $parent_binlog_offset_dto = $this->binlog_history_service->getParentBinlogOffset();
        $this->logger->info("Previous ParentBinlogOffset: " . $parent_binlog_offset_dto);
        $this->logger->info("Previous Child GtidOffsetRanges: ");
        $this->printChildGtidOffsetRanges($this->logger);

        $last_gtid_offset_range_dto = $new_dtos[$new_dtos_count - 1];
        $parent_binlog_date = $this->getNextGtidFirstEventBinlogDate($last_gtid_offset_range_dto->end_dto);
        $this->binlog_history_service->transactional(
            function () use ($new_dtos, $parent_binlog_date): void {
                $this->binlog_history_service->deleteAllChildGtidOffsetRanges();
                $this->insertTotalBinlogOffsetRange($parent_binlog_date, $new_dtos);
            }
        );

        return count($new_dtos);
    }

    private function processContinue(Logger $logger, BinlogPartitionerConfig $partitioner_config): int
    {
        $existed_gtid_partition_count = $this->getChildGtidOffsetRangeCount();
        $extra_gtid_partition_count = $partitioner_config->gtid_partition_max_count - $existed_gtid_partition_count;
        if ($extra_gtid_partition_count <= 0) {
            return $existed_gtid_partition_count;
        }
        $new_start_gtid_offset_dto = $this->getParentBinlogOffset();
        $logger->info("Current BinlogOffset: {$new_start_gtid_offset_dto}");
        $new_dtos = $this->calculateChildGtidOffsetRangeDtos($extra_gtid_partition_count, $new_start_gtid_offset_dto);
        $count_new_dtos = count($new_dtos);

        if ($count_new_dtos > 0) {
            $last_gtid_offset_range_dto = $new_dtos[$count_new_dtos - 1];
            $parent_binlog_date = $this->getNextGtidFirstEventBinlogDate($last_gtid_offset_range_dto->end_dto);
            $this->insertTotalBinlogOffsetRange($parent_binlog_date, $new_dtos);

            return ($existed_gtid_partition_count + $count_new_dtos);
        }

        if ($existed_gtid_partition_count === 0) {
            throw new MsgException("분석할 데이터가 없습니다. (binlog_offset_range_dtos's count == 0)");
        }

        return $existed_gtid_partition_count;
    }

    /**
     * @param BinlogPartitionerConfig $partitioner_config
     * @param array                   $argv
     *
     * @return int
     * @throws MsgException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function processChangeRangePosCommand(BinlogPartitionerConfig $partitioner_config, array $argv): int
    {
        $start_binlog_offset_dto = $this->replication_query->convertToBinlogOffsetDto($argv[2], $argv[3]);
        $end_binlog_offset_dto = $this->replication_query->convertToBinlogOffsetDto($argv[4], $argv[5]);
        if ($start_binlog_offset_dto->compareTo($end_binlog_offset_dto) >= 0) {
            throw new MsgException('range 범위 잘못되었습니다');
        }

        $gtid_partition_max_count = $partitioner_config->gtid_partition_max_count;
        $new_dtos = $this->calculateChildGtidOffsetRangeDtosByRange(
            $gtid_partition_max_count,
            $start_binlog_offset_dto,
            $end_binlog_offset_dto
        );
        $new_dtos_count = count($new_dtos);
        if ($new_dtos_count === 0) {
            throw new MsgException("분석할 데이터가 없습니다. (binlog_offset_range_dtos's count == 0)");
        }

        $parent_binlog_offset_dto = $this->binlog_history_service->getParentBinlogOffset();
        $this->logger->info("Previous ParentBinlogOffset: " . $parent_binlog_offset_dto);
        $this->logger->info("Previous Child GtidOffsetRanges: ");
        $this->printChildGtidOffsetRanges($this->logger);

        $last_gtid_offset_range_dto = $new_dtos[$new_dtos_count - 1];
        $parent_binlog_date = $this->getNextGtidFirstEventBinlogDate($last_gtid_offset_range_dto->end_dto);
        $this->binlog_history_service->transactional(
            function () use ($new_dtos, $parent_binlog_date): void {
                $this->binlog_history_service->deleteAllChildGtidOffsetRanges();
                $this->insertTotalBinlogOffsetRange($parent_binlog_date, $new_dtos);
            }
        );

        return count($new_dtos);
    }

    /**
     * @param array $argv
     *
     * @throws MsgException
     */
    private function assertExecuteCommand(array $argv)
    {
        if (count($argv) >= 2) {
            switch ($argv[1]) {
                case 'change_pos':
                    if (count($argv) !== 4) {
                        throw new MsgException('wrong command');
                    }

                    return;
                case 'change_range_pos':
                    if (count($argv) !== 6) {
                        throw new MsgException('wrong command');
                    }

                    return;
                case 'continue':
                    return;
                case 'child_list':
                    return;
                default:
                    throw new MsgException('wrong command');
            }
        }

        throw new MsgException('wrong command');
    }

    private function printExecuteUsage(string $php_file)
    {
        print("##########################################################################################\n");
        print("Usage:\n");

        print("1. db 저장 위치 다음부터 시작\n");
        print("    php {$php_file} continue\n");
        print("\n");

        print("2. 유저 입력 위치 다음부터 시작\n");
        print("    php {$php_file} change_pos [binLogFileName] [binLogPosition]\n");
        print("ex) php {$php_file} change_pos mariadb-bin.000003 36755\n");
        print("\n");
        print("3. 유저 입력 범위 지정\n");
        print("    php {$php_file} change_range_pos [startBinLogFileName] [startBinLogPosition] [endBinLogFileName] [endBinLogPosition]\n");
        print("ex) php {$php_file} change_range_pos mariadb-bin.000003 36755 mariadb-bin.000004 55\n");
        print("\n");
        print("4. child 리스트 조회\n");
        print("    php {$php_file} child_list\n");
        print("##########################################################################################\n");
    }

    /**
     * @param string                   $parent_binlog_date
     * @param OnlyGtidOffsetRangeDto[] $gtid_offset_range_dtos
     */
    private function insertTotalBinlogOffsetRange(string $parent_binlog_date, array $gtid_offset_range_dtos)
    {
        $this->binlog_history_service->transactional(
            function () use ($gtid_offset_range_dtos, $parent_binlog_date) {
                foreach ($gtid_offset_range_dtos as $gtid_offset_range_dto) {
                    $this->binlog_history_service->insertChildGtidOffsetRange($gtid_offset_range_dto);
                }
                $last_gtid_offset_range_dto = $gtid_offset_range_dtos[count($gtid_offset_range_dtos) - 1];
                $this->binlog_history_service->upsertParentBinlogOffset(
                    $last_gtid_offset_range_dto->exportToEndBinlogOffset(),
                    $parent_binlog_date
                );
            }
        );
    }

    private function getParentBinlogOffset(): BinlogOffsetDto
    {
        $parent_binlog_offset_dto = $this->binlog_history_service->getParentBinlogOffset();

        return $this->replication_query->convertToBinlogOffsetDto(
            $parent_binlog_offset_dto->file_name,
            $parent_binlog_offset_dto->position
        );
    }

    private function getChildGtidOffsetRangeCount(): int
    {
        return $this->binlog_history_service->getChildGtidOffsetRangeCount();
    }

    /**
     * @return OnlyGtidOffsetRangeDto[]
     */
    public function getChildGtidOffsetRanges(): array
    {
        return $this->binlog_history_service->getChildGtidOffsetRanges();
    }

    /**
     * @param int             $gtid_partition_max_count
     * @param BinlogOffsetDto $start_binlog_offset_dto
     *
     * @return OnlyGtidOffsetRangeDto[]
     * @throws MsgException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function calculateChildGtidOffsetRangeDtos(
        int $gtid_partition_max_count,
        BinlogOffsetDto $start_binlog_offset_dto
    ): array {

        //각각은 서로 최소 한개 이후의 GTID 위치를 보장
        $next_gtid_offset_dtos = $this->binlog_event_partition_service->calculateGtidOffsetDtos(
            $gtid_partition_max_count,
            $start_binlog_offset_dto
        );

        $next_dtos_count = count($next_gtid_offset_dtos);
        if ($next_dtos_count < $gtid_partition_max_count) {
            $current_partition_count = $next_dtos_count + 1;
            $next_gtid_offset_dto = $this->binlog_event_partition_service->calculateLastSecondGtidOffsetDto(
                $current_partition_count,
                ($next_dtos_count > 0) ? $next_gtid_offset_dtos[$next_dtos_count - 1] : $start_binlog_offset_dto
            );
            if ($next_gtid_offset_dto !== null) {
                $next_gtid_offset_dtos[] = $next_gtid_offset_dto;
            }
        }

        $dtos = [];
        $current_gtid_offset_dto = $start_binlog_offset_dto;
        foreach ($next_gtid_offset_dtos as $next_gtid_offset_dto) {
            $current_date = $this->getNextGtidFirstEventBinlogDate($current_gtid_offset_dto);
            $dtos[] = OnlyGtidOffsetRangeDto::importFromBinlogOffsets(
                $current_gtid_offset_dto,
                $next_gtid_offset_dto,
                $current_date
            );
            $current_gtid_offset_dto = $next_gtid_offset_dto;
        }

        return $dtos;
    }

    /**
     * @param int             $gtid_partition_max_count
     * @param BinlogOffsetDto $start_binlog_offset_dto
     * @param BinlogOffsetDto $end_binlog_offset_dto
     *
     * @return OnlyGtidOffsetRangeDto[]
     * @throws MsgException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function calculateChildGtidOffsetRangeDtosByRange(
        int $gtid_partition_max_count,
        BinlogOffsetDto $start_binlog_offset_dto,
        BinlogOffsetDto $end_binlog_offset_dto
    ): array {

        $next_gtid_offset_dtos = $this->binlog_event_partition_service->calculateGtidOffsetDtos(
            $gtid_partition_max_count,
            $start_binlog_offset_dto,
            $end_binlog_offset_dto
        );

        $next_dtos_count = count($next_gtid_offset_dtos);
        if ($next_dtos_count < $gtid_partition_max_count) {
            $last_start_dto = ($next_dtos_count > 0) ? $next_gtid_offset_dtos[$next_dtos_count - 1] : $start_binlog_offset_dto;
            if ($last_start_dto->compareTo($end_binlog_offset_dto) < 0) {
                $next_gtid_offset_dtos[] = $end_binlog_offset_dto;
            }
        }

        $dtos = [];
        $current_gtid_offset_dto = $start_binlog_offset_dto;
        foreach ($next_gtid_offset_dtos as $next_gtid_offset_dto) {
            $current_date = $this->getNextGtidFirstEventBinlogDate($current_gtid_offset_dto);
            $dtos[] = OnlyGtidOffsetRangeDto::importFromBinlogOffsets(
                $current_gtid_offset_dto,
                $next_gtid_offset_dto,
                $current_date
            );
            $current_gtid_offset_dto = $next_gtid_offset_dto;
        }

        return $dtos;
    }

    /**
     * 다음 Gtid의 첫번째 이벤트의 Binlog Date를 반환한다
     *
     * @param OnlyBinlogOffsetDto $current_binlog_offset_dto
     *
     * @return string
     * @throws MsgException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getNextGtidFirstEventBinlogDate(OnlyBinlogOffsetDto $current_binlog_offset_dto): string
    {
        try {
            $subscriber = new GetInitBinlogDateSubscriber();
            $binlog_file = $current_binlog_offset_dto->file_name;
            $position = $current_binlog_offset_dto->position;
            $gtid = $this->replication_query->convertToBinlogOffsetDto($binlog_file, $position)->mariadb_gtid;
            $config = BinlogConfiguration::createCustomConnectConfigWithReplace(
                $this->binlog_connect_array,
                [
                    'slaveId' => $this->binlog_history_service->getTemporarySlaveId(),
                    'mariaDbGtid' => $gtid,
                ]
            );
            $binlog_stream = new MySQLReplicationFactory($config);
            $binlog_stream->registerSubscriber($subscriber);

            while (true) {
                $binlog_stream->consume();
                $date = $subscriber->getCurrentBinlogDate();
                if ($date !== null) {
                    $binlog_stream->getDbConnection()->close();

                    return $date;
                }
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            throw new MsgException("not found binlogDate: {$current_binlog_offset_dto}-{$message}");
        }

        throw new MsgException("not found binlogDate: {$current_binlog_offset_dto}");
    }
}
