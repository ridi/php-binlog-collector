<?php

namespace Binlog\Collector\Model;

use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\Library\DB\GnfConnectionProvider;
use Gnf\db\base;

/**
 * Class BinlogHistoryBaseModel
 * @package Binlog\Collector\Model
 */
abstract class BinlogHistoryBaseModel
{
	/**
	 * @var base
	 */
	protected $db;

	private function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param base $db
	 *
	 * @return static
	 */
	private static function create(base $db)
	{
		return new static($db);
	}

	/**
	 * @return static
	 */
	public static function createBinlogHistoryWrite()
	{
		return self::create(GnfConnectionProvider::getGnfConnection(BinlogEnvConfig::HISTORY_WRITE_DB));
	}

	public function transactional(callable $callable)
	{
		return $this->db->transactional($callable);
	}
}
