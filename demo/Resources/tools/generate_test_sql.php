<?php

const TOTAL_BATCH_ROW_COUNT = 100;
const ROW_COUNT_PER_LINE = 10;

function makeTestTargetInsertSqlBulk(string $database_name, string $table_name, string $admin_id): string
{
    $sql = "INSERT INTO {$database_name}.{$table_name} (admin_id) VALUES\n";

    for ($i = 1; $i <= TOTAL_BATCH_ROW_COUNT; $i++) {
        $sql .= "('{$admin_id}')";
        $sql .= ($i === TOTAL_BATCH_ROW_COUNT) ? ';' : ',';
        if ($i % ROW_COUNT_PER_LINE === 0) {
            $sql .= "\n";
        }
    }

    return $sql;
}

function makeTestTargetInsertSqlNotBulk(string $database_name, string $table_name, string $admin_id): string
{
    $queries = [];
    for ($i = 1; $i <= TOTAL_BATCH_ROW_COUNT; $i++) {
        $queries[] = "INSERT INTO {$database_name}.{$table_name} (admin_id) VALUES ('{$admin_id}');";
    }

    return implode("\n", $queries);
}

function makeTestTargetInsertSql(string $database_name, string $table_name, string $admin_id): string
{
    $sql = "INSERT INTO {$database_name}.{$table_name} (admin_id) VALUES ('{$admin_id}');";

    return $sql;
}

function makeTestTargetUpdateSql(
    string $database_name,
    string $table_name,
    int $data_postfix_index,
    array $admin_ids
): string {
    $update_data = sprintf(
        "UPDATE_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_%04d",
        $data_postfix_index
    );
    $sql = "UPDATE {$database_name}.{$table_name} SET data = '{$update_data}' WHERE admin_id IN ('" . implode(
            ',',
            $admin_ids
        ) . "');";

    return $sql;
}

function makeTestTargetDeleteSql(
    string $database_name,
    string $table_name,
    array $admin_ids
): string {
    $sql = "DELETE FROM {$database_name}.{$table_name} WHERE admin_id IN ('" . implode(',', $admin_ids) . "');";

    return $sql;
}


$sql = <<<SQL
CREATE DATABASE IF NOT EXISTS binlog_sample1;
CREATE DATABASE IF NOT EXISTS binlog_sample2;
CREATE DATABASE IF NOT EXISTS binlog_sample3;
CREATE DATABASE IF NOT EXISTS binlog_sample4;
CREATE TABLE IF NOT EXISTS binlog_sample1.test_target1 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0001',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0002', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample1.test_no_target1 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0003',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0004', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample2.test_target2 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0005',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0006', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample2.test_no_target2 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0007',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0008', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample3.test_target3 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0005',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0006', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample3.test_no_target3 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0007',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0008', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample4.test_target4 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0005',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0006', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
CREATE TABLE IF NOT EXISTS binlog_sample4.test_no_target4 (id int NOT NULL AUTO_INCREMENT, data VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0007',data2 VARCHAR(255) DEFAULT 'INSERT_DAT_1000_0000_2000_0000_3000_0000_4000_0000_5000_0000_6000_0000_7000_0000_8000_0000_9000_0000_1000_0000_1100_0000_1200_0000_1300_0000_1400_0000_1500_0000_1600_0000_1700_0000_1800_0000_1900_0008', admin_id VARCHAR(255), PRIMARY KEY(id),  KEY(admin_id));
TRUNCATE TABLE binlog_sample1.test_target1;
TRUNCATE TABLE binlog_sample1.test_no_target1;
TRUNCATE TABLE binlog_sample2.test_target2;
TRUNCATE TABLE binlog_sample2.test_no_target2;
TRUNCATE TABLE binlog_sample3.test_target3;
TRUNCATE TABLE binlog_sample3.test_no_target3;
TRUNCATE TABLE binlog_sample4.test_target4;
TRUNCATE TABLE binlog_sample4.test_no_target4;
SQL;

/**
 *
 * show binlog events in 'mariadb-bin.000004'
 *
 * -- https://mariadb.com/kb/en/the-mariadb-library/data-type-storage-requirements/
 * -- id int, VARCHAR(255)+200bytes, VARCHAR(255)+200bytes, VARCHAR(255)+10bytes
 * -- Insert Data 1개: 4+201+201+11 = 417 bytes
 * -- bulk Insert 100개씩: 417 * 100 = 41,700 bytes
 * -- Total Bulk Insert 800개: 41,700 * 800 =  33,360,000 bytes
 * -- bulk Update 100개씩: 417 * 100 * 2 = 83,400 bytes (before/after)
 * -- Total Bulk Insert 800개: 83,400 * 800 =  66,720,000 bytes
 * -- bulk delete 100개씩: 417 * 100 = 41,700 bytes
 * -- Total Bulk delete 800개: 41,700 * 800 =  33,360,000 bytes
 *
 * 'mariadb-bin.000004', '8695', 'Gtid', '1', '8733', 'BEGIN GTID 0-1-7158'
 * 'mariadb-bin.000004', '52153', 'Xid', '1', '52180', 'COMMIT -- xid=42'
 * ...
 * 'mariadb-bin.000004', '34797868', 'Xid', '1', '34797895', 'COMMIT -- xid=841'
 * Insert Data 1개: 43,485 bytes
 * Insert Finished: 34,789,200 bytes
 *
 * 'mariadb-bin.000004', '34797895', 'Gtid', '1', '34797933', 'BEGIN GTID 0-1-7958'
 * 'mariadb-bin.000004', '34884803', 'Xid', '1', '34884830', 'COMMIT -- xid=842'
 * 'mariadb-bin.000004', '104347068', 'Xid', '1', '104347095', 'COMMIT -- xid=1641 '
 * Update Data 1개:86,935 bytes
 * Update Finished: 69,549,200 bytes
 *
 * 'mariadb-bin.000004', '104347095', 'Gtid', '1', '104347133', 'BEGIN GTID 0-1-8758'
 * 'mariadb-bin.000004', '104390553', 'Xid',  '1', '104390580', 'COMMIT -- xid=1642 '
 * 'mariadb-bin.000004', '104868933', 'Rotate', '1', '104868978', 'mariadb-bin.000005;pos=4'
 * 'mariadb-bin.000005', '4', 'Format_desc', '1', '248', 'Server ver: 10.0.27-MariaDB-1~jessie, Binlog ver: 4'
 * 'mariadb-bin.000005', '34267704', 'Xid', '1', '34267731', 'COMMIT -- xid=2441 '
 * Delete Data 1개: 43,485 bytes
 * Delete Finished: 521,883 + 34,267,727 = 34,789,610 bytes
 */
print($sql . "\n");
$queries = [];
for ($j = 1; $j <= 100; $j++) {
    $admin_id = sprintf("admin_%05d", $j);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample1', 'test_target1', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample1', 'test_no_target1', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample2', 'test_target2', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample2', 'test_no_target2', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample3', 'test_target3', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample3', 'test_no_target3', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample4', 'test_target4', $admin_id);
    $queries[] = makeTestTargetInsertSqlBulk('binlog_sample4', 'test_no_target4', $admin_id);
}
print(implode("\n", $queries));
$queries = [];
for ($j = 1; $j <= 100; $j++) {
    $admin_id = sprintf("admin_%05d", $j);
    $queries[] = makeTestTargetUpdateSql('binlog_sample1', 'test_target1', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample1', 'test_no_target1', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample2', 'test_target2', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample2', 'test_no_target2', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample3', 'test_target3', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample3', 'test_no_target3', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample4', 'test_target4', $j, [$admin_id]);
    $queries[] = makeTestTargetUpdateSql('binlog_sample4', 'test_no_target4', $j, [$admin_id]);
}
print(implode("\n", $queries));
$queries = [];
for ($j = 1; $j <= 100; $j++) {
    $admin_id = sprintf("admin_%05d", $j);
    $queries[] = makeTestTargetDeleteSql('binlog_sample1', 'test_target1', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample1', 'test_no_target1', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample2', 'test_target2', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample2', 'test_no_target2', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample3', 'test_target3', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample3', 'test_no_target3', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample4', 'test_target4', [$admin_id]);
    $queries[] = makeTestTargetDeleteSql('binlog_sample4', 'test_no_target4', [$admin_id]);
}
print(implode("\n", $queries));

// 맨 마지막 GTID는 분석을 안하기 때문에 INSERT 하나 추가
$queries = [];
$queries [] = makeTestTargetInsertSql('binlog_sample1', 'test_target1', 'admin_00001');
print("\n".implode("\n", $queries));
