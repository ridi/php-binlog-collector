<?php

namespace Binlog\Collector\Monitor\Dto;

class TimeMonitorDto
{
    /** @var int */
    public $id;
    /** @var string */
    public $type;
    /** @var int seconds */
    public $elapsed_time;
    /** @var string */
    public $reg_date;

    public static function importFromDatabase(array $dict): self
    {
        $dto = new self();
        $dto->id = intval($dict['id']);
        $dto->type = $dict['type'];
        $dto->elapsed_time = intval($dict['elapsed_time']);
        $dto->reg_date = $dict['reg_date'];

        return $dto;
    }

    public static function importFromElapsedTime(string $type, int $elapsed_time): self
    {
        $dto = new self();

        $dto->type = $type;
        $dto->elapsed_time = $elapsed_time;

        return $dto;
    }

    public function exportToDatabase(): array
    {
        return [
            'type' => $this->type,
            'elapsed_time' => $this->elapsed_time
        ];
    }
}
