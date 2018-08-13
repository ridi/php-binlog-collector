<?php

namespace Binlog\Collector\Application;

use Binlog\Collector\BinlogHistoryGtidChildUpdater;
use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Exception\MsgException;
use Binlog\Collector\Library\DB\GnfConnectionProvider;
use Monolog\Logger;

class BinlogHistoryGtidUpdaterApplication
{
    private const PARTITION_COUNT = 10;
    private const MAX_COUNT_PER_CHILD = 100000;
    private const MIN_COUNT_PER_CHILD = 1000;

    /** @var BinlogConfiguration */
    private $binlog_configuration;
    /** @var Logger */
    private $logger;

    public function __construct(BinlogConfiguration $binlog_configuration)
    {
        $this->binlog_configuration = $binlog_configuration;
        $this->logger = $this->binlog_configuration->exception_handler->getLogger();
    }

    public function executeUpdate(): void
    {
        $start_time = time();
        $this->logger->info('executeMain Started');
        try {
            [$partition_id_to_start_binlog_id, $partition_binlog_range] = $this->initialize();
            GnfConnectionProvider::closeAllConnections();
            $child_pid_to_partition_id = [];
            foreach ($partition_id_to_start_binlog_id as $partition_id => $start_binlog_id) {
                // $pid == -1: fork 실패, $pid == 양수: 부모 프로세스 안, $pid == 0: 자식 프로세스 안
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->logger->info("Child fork failed!");
                } elseif ($pid === 0) {
                    $this->executeChildProcessAndExit($partition_id, $start_binlog_id, $partition_binlog_range);
                } elseif ($pid > 0) {
                    // we are the parent
                    $child_pid_to_partition_id[$pid] = $partition_id;
                    $this->logger->info(
                        "Child(partition_id:{$partition_id}, pid:" . $pid . " start_binlog_id: {$start_binlog_id} started!"
                    );
                }
            }

            /*
             * blocking 방식임.
             * 전체 child process를 loop 돌면서 기다림
             */
            $child_pid = pcntl_waitpid(0, $status);
            while ($child_pid !== -1) {
                $status = pcntl_wexitstatus($status);
                $partition_id = $child_pid_to_partition_id[$child_pid];
                $this->logger->info(
                    "Child(partition_id:{$partition_id}, pid:{$child_pid}, status:{$status}) completed!"
                );
                $child_pid = pcntl_waitpid(0, $status);
            }
        } catch (\Exception $e) {
            $this->logger->info("Exception: " . $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->info("Throwable: " . $e->getMessage());
        }
        $elapsed = time() - $start_time;
        $this->logger->info("executeMain Finished:{$elapsed}s");
    }

    private function initialize(): array
    {
        $total_binlog_count = $this->binlog_configuration->binlog_history_service->getEmptyGtidBinlogCount();
        if ($total_binlog_count === 0) {
            throw new MsgException('There is no empty gtid binlog history');
        }

        $partition_binlog_range = $this->getPartitionBinlogRange($total_binlog_count);
        $partition_count = self::PARTITION_COUNT;
        $this->logger->info("total binlog's count: {$total_binlog_count}, partition_count: {$partition_count}");

        $partition_id_to_start_binlog = $this->getPartitionIdToStartBinlogIdMap($partition_binlog_range);

        return [$partition_id_to_start_binlog, $partition_binlog_range];
    }

    public function getPartitionIdToStartBinlogIdMap(int $partition_binlog_range): array
    {
        $binlog_history_service = $this->binlog_configuration->binlog_history_service;
        $partition_id_to_start_binlog_id = [];
        $binlog_id = $binlog_history_service->getRecentEmptyGtidBinlogId();
        if ($binlog_id === 0) {
            return $partition_id_to_start_binlog_id;
        }
        $offset = $partition_binlog_range - 1;
        $next_binlog_id = $binlog_id;
        $partition_id_to_start_binlog_id[0] = $next_binlog_id;
        for ($partition_id = 1; $partition_id < self::PARTITION_COUNT; $partition_id++) {
            $next_binlog_id = $binlog_history_service->getEmptyGtidBinlogIdByLesserIdAndOffset(
                $next_binlog_id,
                $offset
            );
            if ($next_binlog_id === 0) {
                break;
            }
            $partition_id_to_start_binlog_id[$partition_id] = $next_binlog_id;
        }

        return $partition_id_to_start_binlog_id;
    }

    public function getPartitionBinlogRange(int $total_binlog_count): int
    {
        $partition_binlog_range = ceil($total_binlog_count / self::PARTITION_COUNT);
        if (self::MAX_COUNT_PER_CHILD < $partition_binlog_range) {
            $partition_binlog_range = self::MAX_COUNT_PER_CHILD;
        }
        if ($partition_binlog_range < self::MIN_COUNT_PER_CHILD) {
            $partition_binlog_range = self::MIN_COUNT_PER_CHILD;
        }

        return $partition_binlog_range;
    }

    public function executeChildProcessAndExit(int $partition_id, int $start_binlog_id, int $max_binlog_count): void
    {
        $connect_config = $this->binlog_configuration->createConnectConfig();
        $binlog_history_gtid_child_update = null;
        try {
            $binlog_history_gtid_child_update = new BinlogHistoryGtidChildUpdater($this->logger, $connect_config);
            $binlog_history_gtid_child_update->execute($start_binlog_id, $max_binlog_count);
        } catch (\Exception $exception) {
            $this->binlog_configuration->exception_handler->triggerException($exception);
            $message = $exception->getMessage();
            $this->logger->info("Child(partition_id:{$partition_id}) Exception: {$message}");
        } catch (\Throwable $throwable) {
            $this->binlog_configuration->exception_handler->triggerMessage($throwable->getMessage());
            $message = $throwable->getMessage();
            $this->logger->info("Child(partition_id:{$partition_id}) Exception: {$message}");
        }
        if ($binlog_history_gtid_child_update !== null) {
            $binlog_history_gtid_child_update->close();
        }
        GnfConnectionProvider::closeAllConnections();
        exit();
    }
}
