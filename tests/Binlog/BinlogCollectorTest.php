<?php

namespace Binlog\Tests;

use Binlog\Collector\Model\BinlogHistoryModel;
use PHPUnit\Framework\TestCase;

class BinlogCollectorTest extends TestCase
{
    /*
     * from my.master.init_test.sql
     * binlog(1200)   : 800/2+800/2+800/2=1200
     * row(120,000)   : insert/update/delete 800/2*100 + 800/2*100 + 800/2*100= 120,000
     * COL(360,000): insert/delete: 40,000*field(4) + 40,000*field(4) + update: 40,000+changed_field(1)
     *                   = 160,000 + 160,000 + 40,000 = 360,000
     */
    const BINLOG_TOTAL_COUNT = 1200;
    const WRITE_BINLOG_ROW_COUNT = 40000;
    const UPDATE_BINLOG_ROW_COUNT = 40000;
    const DELETE_BINLOG_ROW_COUNT = 40000;
    const BINLOG_ROW_COUNT = self::WRITE_BINLOG_ROW_COUNT + self::UPDATE_BINLOG_ROW_COUNT + self::DELETE_BINLOG_ROW_COUNT;
    const WRITE_BINLOG_COL_COUNT = 160000;
    const UPDATE_BINLOG_COL_COUNT = 40000;
    const DELETE_BINLOG_COL_COUNT = 160000;
    const BINLOG_COL_COUNT = self::WRITE_BINLOG_COL_COUNT + self::UPDATE_BINLOG_COL_COUNT + self::DELETE_BINLOG_COL_COUNT;

    const TARGET_TABLES = [
        'binlog_sample1.test_target1',
        'binlog_sample2.test_target2',
        'binlog_sample3.test_target3',
        'binlog_sample4.test_target4'
    ];

    public function testBinlogCollectResult()
    {
        $binlog_history_model = BinlogHistoryModel::createBinlogHistoryWrite();

        $binlog_count = $binlog_history_model->getBinlogCount(['id' => sqlNot(sqlNull())]);
        $binlog_row_count = $binlog_history_model->getBinlogRowCount(['u_row.id' => sqlNot(sqlNull())]);
        $binlog_col_count = $binlog_history_model->getBinlogColumnCount(['u_column.id' => sqlNot(sqlNull())]);

        print("binlog_total_count: {$binlog_count}\n");
        print("binlog_total_row_count: {$binlog_row_count}\n");
        print("binlog_total_col_count: {$binlog_col_count}\n");

        $this->assertEquals($binlog_count, self::BINLOG_TOTAL_COUNT);
        $this->assertEquals($binlog_row_count, self::BINLOG_ROW_COUNT);
        $this->assertEquals($binlog_col_count, self::BINLOG_COL_COUNT);

        $write_row_count = $binlog_history_model->getBinlogRowCount(
            ['u_row.table_name' => self::TARGET_TABLES, 'u_row.action' => 'write']
        );
        $update_row_count = $binlog_history_model->getBinlogRowCount(
            ['u_row.table_name' => self::TARGET_TABLES, 'u_row.action' => 'update']
        );
        $delete_row_count = $binlog_history_model->getBinlogRowCount(
            ['u_row.table_name' => self::TARGET_TABLES, 'u_row.action' => 'delete']
        );

        print("write_binlog_row_count: {$write_row_count}\n");
        print("update_binlog_row_count: {$update_row_count}\n");
        print("delete_binlog_row_count: {$delete_row_count}\n");

        $this->assertEquals($write_row_count, self::WRITE_BINLOG_ROW_COUNT);
        $this->assertEquals($update_row_count, self::UPDATE_BINLOG_ROW_COUNT);
        $this->assertEquals($delete_row_count, self::DELETE_BINLOG_ROW_COUNT);

        $write_col_count = $binlog_history_model->getBinlogColumnCount(
            ['u_row.table_name' => self::TARGET_TABLES, 'u_row.action' => 'write']
        );
        $update_col_count = $binlog_history_model->getBinlogColumnCount(
            ['u_row.table_name' => self::TARGET_TABLES, 'u_row.action' => 'update']
        );
        $delete_col_count = $binlog_history_model->getBinlogColumnCount(
            ['u_row.table_name' => self::TARGET_TABLES, 'u_row.action' => 'delete']
        );

        print("write_binlog_col_count: {$write_col_count}\n");
        print("update_binlog_col_count: {$update_col_count}\n");
        print("delete_binlog_col_count: {$delete_col_count}\n");

        $this->assertEquals($write_col_count, self::WRITE_BINLOG_COL_COUNT);
        $this->assertEquals($update_col_count, self::UPDATE_BINLOG_COL_COUNT);
        $this->assertEquals($delete_col_count, self::DELETE_BINLOG_COL_COUNT);
    }
}
