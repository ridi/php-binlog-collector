<?php

use Binlog\Collector\Model\BinlogHistoryModel;

require_once __DIR__ . "/../include/bootstrap_binlog_collector.php";


/*
 * from my.master.init_test.sql
 * binlog(1200)   : 800/2+800/2+800/2=1200
 * row(120,000)   : insert/update/delete 800/2*100 + 800/2*100 + 800/2*100= 120,000
 * COL(360,000): insert/delete: 40,000*field(4) + 40,000*field(4) + update: 40,000+changed_field(1)
 *                   = 160,000 + 160,000 + 40,000 = 360,000
 */
$BINLOG_TOTAL_COUNT = 1200;
$WRITE_BINLOG_ROW_COUNT = 40000;
$UPDATE_BINLOG_ROW_COUNT = 40000;
$DELETE_BINLOG_ROW_COUNT = 40000;
$BINLOG_ROW_COUNT = $WRITE_BINLOG_ROW_COUNT + $UPDATE_BINLOG_ROW_COUNT + $DELETE_BINLOG_ROW_COUNT;
$WRITE_BINLOG_COL_COUNT = 160000;
$UPDATE_BINLOG_COL_COUNT = 40000;
$DELETE_BINLOG_COL_COUNT = 160000;
$BINLOG_COL_COUNT = $WRITE_BINLOG_COL_COUNT + $UPDATE_BINLOG_COL_COUNT + $DELETE_BINLOG_COL_COUNT;

$TARGET_TABLES = [
    'binlog_sample1.test_target1',
    'binlog_sample2.test_target2',
    'binlog_sample3.test_target3',
    'binlog_sample4.test_target4'
];
$binlog_history_model = BinlogHistoryModel::createBinlogHistoryWrite();

$binlog_count = $binlog_history_model->getBinlogCount(['id' => sqlNot(sqlNull())]);
$binlog_row_count = $binlog_history_model->getBinlogRowCount(['u_row.id' => sqlNot(sqlNull())]);
$binlog_col_count = $binlog_history_model->getBinlogColumnCount(['u_column.id' => sqlNot(sqlNull())]);

print("binlog_total_count: {$binlog_count}\n");
print("binlog_total_row_count: {$binlog_row_count}\n");
print("binlog_total_col_count: {$binlog_col_count}\n");

if ($binlog_count !== $BINLOG_TOTAL_COUNT
    || $binlog_row_count !== $BINLOG_ROW_COUNT
    || $binlog_col_count !== $BINLOG_COL_COUNT
) {
    print("error!\n");
} else {
    print("success!\n");
}

$write_row_count = $binlog_history_model->getBinlogRowCount(
    ['u_row.table_name' => $TARGET_TABLES, 'u_row.action' => 'write']
);
$update_row_count = $binlog_history_model->getBinlogRowCount(
    ['u_row.table_name' => $TARGET_TABLES, 'u_row.action' => 'update']
);
$delete_row_count = $binlog_history_model->getBinlogRowCount(
    ['u_row.table_name' => $TARGET_TABLES, 'u_row.action' => 'delete']
);

print("write_binlog_row_count: {$write_row_count}\n");
print("update_binlog_row_count: {$update_row_count}\n");
print("delete_binlog_row_count: {$delete_row_count}\n");

if ($write_row_count !== $WRITE_BINLOG_ROW_COUNT
    || $update_row_count !== $UPDATE_BINLOG_ROW_COUNT
    || $delete_row_count !== $DELETE_BINLOG_ROW_COUNT
) {
    print("error!\n");
} else {
    print("success!\n");
}

$write_col_count = $binlog_history_model->getBinlogColumnCount(
    ['u_row.table_name' => $TARGET_TABLES, 'u_row.action' => 'write']
);
$update_col_count = $binlog_history_model->getBinlogColumnCount(
    ['u_row.table_name' => $TARGET_TABLES, 'u_row.action' => 'update']
);
$delete_col_count = $binlog_history_model->getBinlogColumnCount(
    ['u_row.table_name' => $TARGET_TABLES, 'u_row.action' => 'delete']
);

print("write_binlog_col_count: {$write_col_count}\n");
print("update_binlog_col_count: {$update_col_count}\n");
print("delete_binlog_col_count: {$delete_col_count}\n");

if ($write_col_count !== $WRITE_BINLOG_COL_COUNT
    || $update_col_count !== $UPDATE_BINLOG_COL_COUNT
    || $delete_col_count !== $DELETE_BINLOG_COL_COUNT
) {
    print("error!\n");
} else {
    print("success!\n");
}
