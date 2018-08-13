<?php

namespace Binlog\Tests;

use Binlog\Collector\BinlogHistoryGtidChildUpdater;
use PHPUnit\Framework\TestCase;

class BinlogHistoryGtidChildUpdaterTest extends TestCase
{

    public function testSortDescendingByBinlogFileNameAndGtidEndPos(): void
    {
        $dicts[] = $this->makeBinlogDict(4, 'mariadb-bin.007350', 10000001, '', '2017-09-14 17:14:42');
        $dicts[] = $this->makeBinlogDict(2, 'mariadb-bin.007351', 20000001, '', '2017-09-14 17:14:47');
        $dicts[] = $this->makeBinlogDict(3, 'mariadb-bin.007350', 10000002, '', '2017-09-14 17:14:45');
        $dicts[] = $this->makeBinlogDict(1, 'mariadb-bin.007351', 20000002, '', '2017-09-14 17:14:48');

        $dicts = BinlogHistoryGtidChildUpdater::sortDescendingByBinlogFileNameAndGtidEndPos($dicts);
        $this->assertEquals(1, $dicts[0]['id']);
        $this->assertEquals(2, $dicts[1]['id']);
        $this->assertEquals(3, $dicts[2]['id']);
        $this->assertEquals(4, $dicts[3]['id']);
    }

    public function makeBinlogDict(
        int $id,
        string $binlog_filename,
        int $gtid_end_pos,
        string $gtid,
        string $reg_date
    ): array {
        return [
            'id' => $id,
            'binlog_filename' => $binlog_filename,
            'gtid_end_pos' => $gtid_end_pos,
            'gtid' => $gtid,
            'reg_date' => $reg_date,
        ];
    }
}
