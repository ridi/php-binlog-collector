<?php

namespace Binlog\Collector\Monitor\Model;

use Binlog\Collector\Model\BinlogHistoryBaseModel;
use Binlog\Collector\Monitor\Dto\TimeMonitorDto;

class BinlogTimeMonitorModel extends BinlogHistoryBaseModel
{
    public function insertTimeMonitor(TimeMonitorDto $monitor_dto): int
    {
        $this->db->sqlInsert('platform_time_monitor', $monitor_dto->exportToDatabase());

        return $this->db->insert_id();
    }

    public function getTimeMonitor(int $id): ?TimeMonitorDto
    {
        $dict = $this->db->sqlDict('SELECT * FROM platform_time_monitor WHERE ?', sqlWhere(['id' => $id]));

        return ($dict !== null) ? TimeMonitorDto::importFromDatabase($dict) : null;
    }

    public function deleteTimeMonitor(int $id): int
    {
        return $this->db->sqlDelete('platform_time_monitor', ['id' => $id]);
    }

    /**
     * @param string $type {@link TimeMonitorConst}
     *
     * @return string|null
     */
    public function getLastTimeMonitor(string $type): ?string
    {
        $where = ['type' => $type];

        return $this->db->sqlData('SELECT MAX(reg_date) FROM platform_time_monitor WHERE ?', sqlWhere($where));
    }
}
