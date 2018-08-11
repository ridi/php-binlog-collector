<?php

namespace Binlog\Collector\External\Impl;

use Binlog\Collector\External\AbstractRowEventValueSkipper;

class DefaultRowEventValueSkipper extends AbstractRowEventValueSkipper
{
    public function isTargetEventValue(int $binlog_event_timestamp, string $table, string $type, array $value): bool
    {
//        $after_value = [];
//        $before_value = [];
//        switch ($type) {
//            case ConstEventsNames::UPDATE:
//                $before_value = $value['before'];
//                $after_value = $value['after'];
//                break;
//            case ConstEventsNames::WRITE:
//                $after_value = $value;
//                break;
//            case ConstEventsNames::DELETE:
//                $before_value = $value;
//                break;
//        }

        return true;
    }
}
