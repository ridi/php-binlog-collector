<?php

namespace Binlog\Collector\Subscriber;

use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\EventSubscribers;

/**
 * Class GetInitBinlogDateSubscriber
 * @package Binlog\Collector\Subscriber
 */
class GetInitBinlogDateSubscriber extends EventSubscribers
{
	/** @var string|null */
	private $current_binlog_date;

	public function __construct() {}

	protected function allEvents(EventDTO $event)
	{
		$this->current_binlog_date = $event->getEventInfo()->getDateTime();
	}

	/**
	 * @return string|null
	 */
	public function getCurrentBinlogDate()
	{
		return $this->current_binlog_date;
	}
}
