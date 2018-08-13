<?php

namespace Binlog\Collector\Dto;

class BinlogOffsetDto extends OnlyBinlogOffsetDto
{
    /** @var string */
    public $mariadb_gtid;

    public static function importBinlogOffset(string $mariadb_gtid, string $file_name, int $position): self
    {
        $dto = new self();
        $dto->mariadb_gtid = $mariadb_gtid;
        $dto->file_name = $file_name;
        $dto->position = $position;

        return $dto;
    }

    public function __toString(): string
    {
        return "[{$this->mariadb_gtid}/{$this->file_name}/{$this->position}]";
    }
}
