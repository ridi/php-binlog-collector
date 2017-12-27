<?php

namespace Binlog\Collector\Model;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use MySQLReplication\Config\Config;

/**
 * Class ReplicationDbModel
 * @package Binlog\Collector\Model
 */
class ReplicationDbModel
{
    /** @var Connection */
    private $connection;

    public function __construct(Config $config)
    {
        $this->connection = $this->initConnection($config);
    }

    private function initConnection(Config $config): Connection
    {
        $config->validate();

        return DriverManager::getConnection(
            [
                'user' => $config->getUser(),
                'password' => $config->getPassword(),
                'host' => $config->getHost(),
                'port' => empty($config->getPort()) ? 3306 : $config->getPort(),
                'driver' => 'pdo_mysql',
                'charset' => $config->getCharset()
            ]
        );
    }

    public function getConnection(): Connection
    {
        if (false === $this->connection->ping()) {
            $this->connection->close();
            $this->connection->connect();
        }

        return $this->connection;
    }

    public function close()
    {
        $this->connection->close();
    }

    public function getBinlogGtidPos(string $binlog_filename, int $binlog_offset): string
    {
        $gtid = $this->getConnection()->fetchAssoc(
            "SELECT BINLOG_GTID_POS(\"{$binlog_filename}\", {$binlog_offset} ) as mariadb_gtid"
        )['mariadb_gtid'];

        return ($gtid !== null) ? $gtid : '';
    }

    public function showMasterStatus(): array
    {
        return $this->getConnection()->fetchAssoc("SHOW MASTER STATUS");
    }

    public function showBinlogEvents(string $log_name, int $pos, int $offset = 0, int $row_count = 1000): array
    {
        try {
            return $this->getConnection()->fetchAll(
                "SHOW BINLOG EVENTS IN '{$log_name}' FROM {$pos} LIMIT {$offset}, {$row_count}"
            );
        } catch (DriverException $e) {
            return [];
        }
    }

    public function showBinlogEventsFromInit(string $log_name, int $offset = 0, int $row_count = 1000): array
    {
        try {
            return $this->getConnection()->fetchAll(
                "SHOW BINLOG EVENTS IN '{$log_name}' LIMIT {$offset}, {$row_count}"
            );
        } catch (DriverException $e) {
            return [];
        }
    }

    public function showBinlogEventsUsingThrowException(string $log_name): array
    {
        return $this->getConnection()->fetchAll("SHOW BINLOG EVENTS IN '{$log_name}' LIMIT 0, 1");
    }

    public function getTableNames(array $table_schemas): array
    {
        $sql = "SELECT DISTINCT `TABLE_NAME` FROM `information_schema`.`COLUMNS`";

        $where = '';
        if (count($table_schemas) > 0) {
            $where = 'WHERE `TABLE_SCHEMA` in ( "' . implode('","', $table_schemas) . '")';
        }

        return $this->getConnection()->fetchAll($sql . $where);
    }
}
