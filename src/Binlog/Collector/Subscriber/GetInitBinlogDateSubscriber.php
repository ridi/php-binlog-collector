<?php

namespace Binlog\Collector\Subscriber;

use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\EventSubscribers;

class GetInitBinlogDateSubscriber extends EventSubscribers
{
    /** @var string|null */
    private $current_binlog_date;

    public function __construct()
    {
    }

    protected function allEvents(EventDTO $event): void
    {
        if ($event->getType() === ConstEventsNames::FORMAT_DESCRIPTION) {
            return;
        }
        $timestamp = $event->getEventInfo()->getTimestamp();
        $this->current_binlog_date = (new \DateTime())->setTimestamp($timestamp)->format('Y-m-d H:i:s');
    }

    public function getCurrentBinlogDate(): ?string
    {
        return $this->current_binlog_date;
    }
}
