<?php

namespace Binlog\Collector\Application;

use Binlog\Collector\BinlogCollector;
use Binlog\Collector\BinlogCollectorInfo;
use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogWorkerConfig;
use Binlog\Collector\Dto\GtidOffsetRangeDto;
use Binlog\Collector\Dto\OnlyGtidOffsetRangeDto;
use Binlog\Collector\Exception\BinlogFinishedException;
use Binlog\Collector\Exception\MsgException;
use Binlog\Collector\Library\DB\GnfConnectionProvider;
use Binlog\Collector\Model\ReplicationDbModel;
use Binlog\Collector\ReplicationQuery;
use Binlog\Collector\Subscriber\InsertBinlogSubscriber;
use Monolog\Logger;
use MySQLReplication\MySQLReplicationFactory;

class BinlogCollectorApplication
{
    /** @var BinlogConfiguration */
    private $binlog_configuration;
    /** @var Logger */
    private $logger;

    public function __construct(BinlogConfiguration $binlog_configuration)
    {
        $this->binlog_configuration = $binlog_configuration;
        $this->logger = $this->binlog_configuration->exception_handler->getLogger();
    }

    public function getInfo(): void
    {
        $partitioner_config = $this->binlog_configuration->createPartitionerConfig();
        try {
            $this->logger->info("Initialize!");
            $collector_info = new BinlogCollectorInfo();
            $collector_info->getInfo($this->logger, $partitioner_config, $this->binlog_configuration->argv);
        } catch (\Throwable $e) {
            $this->logger->info($e->getMessage() . "\n");
        }
    }

    public function executePartitioning(): void
    {
        $partitioner_config = $this->binlog_configuration->createPartitionerConfig();
        try {
            $this->logger->info("Initialize!");
            $collector = new BinlogCollector($this->binlog_configuration->binlog_history_service);
            $collector->initialize($this->logger, $partitioner_config, $this->binlog_configuration->argv);
        } catch (\Throwable $e) {
            $this->logger->info($e->getMessage() . "\n");
        }
    }

    public function executeWorking(): void
    {
        $worker_config = $this->binlog_configuration->createWorkerConfig();
        try {
            $collector = new BinlogCollector($this->binlog_configuration->binlog_history_service);
            $gtid_offset_range_dtos = $collector->getChildGtidOffsetRanges();
            $this->logger->info('TotalGtidPartitions\'s Count: ' . count($gtid_offset_range_dtos));
        } catch (\Throwable $e) {
            $this->logger->info($e->getMessage() . "\n");
            exit();
        }

        $total_dtos_count = count($gtid_offset_range_dtos);
        $child_pid_to_slave_id = [];
        for ($i = 0; $i < $worker_config->child_process_max_count && $i < $total_dtos_count; $i++) {
            // 리소스를 공유하기 때문에 fork하기 전에 기존 리소스 반환
            GnfConnectionProvider::closeAllConnections();

            $gtid_offset_range_dto = $gtid_offset_range_dtos[$i];
            $child_index = $gtid_offset_range_dto->child_index;
            // $pid == -1: fork 실패, $pid == 양수: 부모 프로세스 안, $pid == 0: 자식 프로세스 안
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->logger->info("binlog_collector.cron: Child fork failed!\n");
            } elseif ($pid == 0) {
                $slave_id = $this->binlog_configuration->binlog_history_service->getChildSlaveId($i);
                $this->executeChildProcess($slave_id, $child_index, $worker_config, $gtid_offset_range_dto);
            } elseif ($pid > 0) {
                // we are the parent
                $slave_id = $this->binlog_configuration->binlog_history_service->getChildSlaveId($i);
                $child_pid_to_slave_id[$pid] = $slave_id;
                // $this->logger->info("binlog_collector.cron: " . $child_index . ". Child(pid: " . $pid . "): start!\n");
            }
        }

        /*
         * blocking 방식임.
         * 전체 child process를 loop 돌면서 기다림
         */
        $child_pid = pcntl_waitpid(0, $status);
        while ($child_pid !== -1) {
            GnfConnectionProvider::closeAllConnections();
            $status = pcntl_wexitstatus($status);
            // $this->logger->info("binlog_collector.cron: Child(status:{$status}): completed!\n");

            if ($i < $total_dtos_count) {
                $gtid_offset_range_dto = $gtid_offset_range_dtos[$i];
                $child_index = $gtid_offset_range_dto->child_index;
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->logger->info("binlog_collector.cron: Child fork failed!\n");
                    $i++;
                } elseif ($pid === 0) {
                    $slave_id = $child_pid_to_slave_id[$child_pid];
                    $this->executeChildProcess($slave_id, $child_index, $worker_config, $gtid_offset_range_dto);
                } elseif ($pid > 0) {
                    // we are the parent
                    $child_pid_to_slave_id[$pid] = $child_pid_to_slave_id[$child_pid];
                    // $this->logger->info("binlog_collector.cron: " . $child_index . ". Child(pid: " . $pid . "): start!\n");
                    $i++;
                }
            }
            $child_pid = pcntl_waitpid(0, $status);
        }
    }

    private function executeChildProcess(
        int $slave_id,
        int $child_index,
        BinlogWorkerConfig $worker_config,
        OnlyGtidOffsetRangeDto $gtid_range_dto
    ): void {
        try {
            $replication_query = new ReplicationQuery(new ReplicationDbModel($worker_config->connect_config));
            $gtid_offset_range_dto = GtidOffsetRangeDto::create($replication_query, $child_index, $gtid_range_dto);
            $this->logger->info("child_index({$child_index}): process started, {$gtid_offset_range_dto->start_dto}");
            $new_binlog_worker_config = $this->binlog_configuration->extendWorkerConfig(
                [
                    'slaveId' => $slave_id,
                    'mariaDbGtid' => $gtid_offset_range_dto->start_dto->mariadb_gtid,
                ],
                [
                    'child_index' => $child_index,
                ]
            );

            $insert_binlog_subscriber = new InsertBinlogSubscriber(
                $this->logger,
                $replication_query,
                $this->binlog_configuration->binlog_history_service,
                $this->binlog_configuration->row_event_value_skipper,
                $new_binlog_worker_config,
                $gtid_offset_range_dto
            );

            $binlog_stream = new MySQLReplicationFactory($new_binlog_worker_config->connect_config);
            $binlog_stream->registerSubscriber($insert_binlog_subscriber);

            while (true) {
                $binlog_stream->consume();
            }
        } catch (BinlogFinishedException $e) {
            $this->logger->info($e->getMessage());
            exit();
        } catch (\Exception $exception) {
            if ($exception->getMessage() !== 'Success') {
                $this->logger->info("child_index({$child_index} : exception({$exception->getMessage()})");
                $this->binlog_configuration->exception_handler->triggerException($exception);
            }
            exit();
        }
        exit();
    }
}
