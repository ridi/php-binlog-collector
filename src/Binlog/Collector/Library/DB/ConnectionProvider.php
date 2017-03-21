<?php

namespace Binlog\Collector\Library\DB;

use Binlog\Collector\Config\BinlogEnvConfig;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Class ConnectionProvider
 * @package Binlog\Collector\Library\DB
 */
class ConnectionProvider
{
	/** @var Connection[] */
	private static $connection_pool = [];

	protected static function getConnection(string $group_name, bool $is_auto_reconnect = false): Connection
	{
		if (!isset(self::$connection_pool[$group_name])) {
			self::$connection_pool[$group_name] = self::createConnection($group_name);
		}

		$connection = self::$connection_pool[$group_name];

		if ($is_auto_reconnect && $connection->ping() === false) {
			$connection->close();
			$connection->connect();
		}

		return $connection;
	}

	/**
	 * @param string $group_name
	 *
	 * @return Connection
	 */
	protected static function createConnection(string $group_name): Connection
	{
		$connection = DriverManager::getConnection(BinlogEnvConfig::getConnectionParams($group_name));
		$connection->setFetchMode(\PDO::FETCH_OBJ);

		return $connection;
	}

	protected static function closeAllConnections()
	{
		foreach (self::$connection_pool as $connection) {
			$connection->close();
		}
		self::$connection_pool = [];
	}
}
