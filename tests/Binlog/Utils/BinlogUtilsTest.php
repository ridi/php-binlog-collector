<?php

namespace Binlog\Tests\Utils;

use Binlog\Collector\BinlogPosFinder;
use Binlog\Collector\Dto\OnlyBinlogOffsetDto;
use Binlog\Collector\Utils\BinlogUtils;
use PHPUnit\Framework\TestCase;

class BinLogUtilsTest extends TestCase
{
    public function testCalculateNextSeqFile(): void
    {
        $actual = BinlogUtils::calculateNextSeqFile('mariadb-bin.000000');
        $this->assertEquals('mariadb-bin.000001', $actual);

        $actual = BinlogUtils::calculateNextSeqFile('mariadb-bin.000015');
        $this->assertEquals('mariadb-bin.000016', $actual);

        $actual = BinlogUtils::calculateNextSeqFile('mariadb-bin.999999');
        $this->assertEquals('mariadb-bin.000000', $actual);
    }

    public function testCalculatePreviousSeqFile(): void
    {
        $actual = BinlogUtils::calculatePreviousSeqFile('mariadb-bin.000016');
        $this->assertEquals('mariadb-bin.000015', $actual);

        $actual = BinlogUtils::calculatePreviousSeqFile('mariadb-bin.000001');
        $this->assertEquals('mariadb-bin.000000', $actual);

        $actual = BinlogUtils::calculatePreviousSeqFile('mariadb-bin.000000');
        $this->assertEquals('mariadb-bin.999999', $actual);
    }

    public function testConvertGtidBinLogInfoToGtid(): void
    {
        $gtid = explode(' ', trim(str_replace(['BEGIN', 'GTID'], '', 'GTID 0-43-14535494')))[0];
        $this->assertEquals('0-43-14535494', $gtid);
        $gtid = explode(' ', trim(str_replace(['BEGIN', 'GTID'], '', 'BEGIN GTID 0-43-14532884 cid=97361316')))[0];
        $this->assertEquals('0-43-14532884', $gtid);
        $gtid = explode(' ', trim(str_replace(['BEGIN', 'GTID'], '', 'BEGIN GTID 0-43-14535495')))[0];
        $this->assertEquals('0-43-14535495', $gtid);
    }


    public function testRemoveSkipServerId(): void
    {
        $gtid = '0-146-1683252403,12-12-19056634,11-11-1887613118';
        $this->assertEquals('0-146-1683252403,11-11-1887613118,12-12-19056634', BinlogPosFinder::sortGtidList($gtid));

        $actual = BinlogPosFinder::removeSkipServerId($gtid, '146');
        $this->assertEquals('11-11-1887613118,12-12-19056634', $actual);

        $actual = BinlogPosFinder::removeSkipServerId($gtid, '12');
        $this->assertEquals('0-146-1683252403,11-11-1887613118', $actual);

        $actual = BinlogPosFinder::removeSkipServerId($gtid, '11');
        $this->assertEquals('0-146-1683252403,12-12-19056634', $actual);
    }

    public function testGetSeqByBinlogFileName(): void
    {
        $this->assertEquals(16, BinLogUtils::getSeqByBinlogFileName('mariadb-bin.000016'));
        $this->assertEquals(0, BinLogUtils::getSeqByBinlogFileName('mariadb-bin.000000'));
    }

    public function testOnlyBinlogOffsetDtoCompareTo(): void
    {
        $only_binlog_offset_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset('mariadb-bin.000001', 11111);

        $this->assertEquals(0, $only_binlog_offset_dto->compareTo($only_binlog_offset_dto));
        $target_binlog_offset_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset('mariadb-bin.000002', 11111);
        $this->assertEquals(-1, $only_binlog_offset_dto->compareTo($target_binlog_offset_dto));
        $target_binlog_offset_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset('mariadb-bin.000000', 11111);
        $this->assertEquals(1, $only_binlog_offset_dto->compareTo($target_binlog_offset_dto));


        $target_binlog_offset_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset('mariadb-bin.000001', 11112);
        $this->assertEquals(-1, $only_binlog_offset_dto->compareTo($target_binlog_offset_dto));
        $target_binlog_offset_dto = OnlyBinlogOffsetDto::importOnlyBinlogOffset('mariadb-bin.000001', 11110);
        $this->assertEquals(1, $only_binlog_offset_dto->compareTo($target_binlog_offset_dto));
    }
}
