#! /bin/bash
php ../cron/\[every\]binlog_collect_partitioner.php change_pos mariadb-bin.000004 4
php ../cron/\[every\]binlog_collect_partitioner.php continue
php ../cron/\[every\]binlog_collect_partitioner.php continue
php ../cron/\[every\]binlog_collect_worker.php
php ../cron/\[every\]binlog_collect_worker.php
php ../cron/\[every\]binlog_collect_worker.php
