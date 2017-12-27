USE mysql;

FLUSH PRIVILEGES;
CHANGE MASTER TO 
MASTER_HOST='slave',
MASTER_USER='repl',
MASTER_PASSWORD='1234',
MASTER_PORT=3306,
MASTER_LOG_FILE='mariadb-bin.000004',
MASTER_LOG_POS=4,
MASTER_CONNECT_RETRY=10;
start slave;
CREATE DATABASE IF NOT EXISTS platform;
use platform;
CREATE TABLE IF NOT EXISTS `platform_universal_history_3_binlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `binlog_filename` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `gtid_end_pos` int(10) unsigned NOT NULL,
  `gtid` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `reg_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `binlog` (`binlog_filename`,`gtid_end_pos`,`reg_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS `platform_universal_history_3_row` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `binlog_id` int(10) unsigned NOT NULL,
  `idx` tinyint(3) unsigned NOT NULL,
  `table_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `pk_ids` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `action` enum('write','update','delete') COLLATE utf8_unicode_ci NOT NULL,
  `reg_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `binlog` (`binlog_id`,`idx`,`table_name`,`pk_ids`),
  KEY `search` (`reg_date`,`table_name`,`pk_ids`),
  CONSTRAINT `fk_binlog` FOREIGN KEY (`binlog_id`) REFERENCES `platform_universal_history_3_binlog` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS `platform_universal_history_3_column` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `row_id` bigint(20) unsigned NOT NULL COMMENT '수정 row No',
  `column` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT '컬럼',
  `data_before` text COLLATE utf8_unicode_ci COMMENT '수정 전',
  `data_after` text COLLATE utf8_unicode_ci COMMENT '수정 후',
  PRIMARY KEY (`id`),
  UNIQUE KEY `row_column` (`row_id`,`column`),
  CONSTRAINT `row_id` FOREIGN KEY (`row_id`) REFERENCES `platform_universal_history_3_row` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=79309 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS `platform_universal_history_child_offset` (
  `child_index` int(10) NOT NULL AUTO_INCREMENT COMMENT '시퀀스',
  `current_bin_log_file_name` varchar(255) NOT NULL COMMENT '마지막으로 처리된 bin_log file_name',
  `current_bin_log_position` int(10) unsigned NOT NULL COMMENT '마지막으로 처리된 bin_log position',
  `end_bin_log_file_name` varchar(255) NOT NULL COMMENT 'end_bin_log file_name 까지 처리 가능',
  `end_bin_log_position` int(10) unsigned NOT NULL COMMENT 'end_bin_log position 까지 처리 가능',
  `current_bin_log_position_date` datetime NOT NULL COMMENT '현재 bin_log_position 날짜',
  PRIMARY KEY (`child_index`)
) ENGINE=InnoDB AUTO_INCREMENT=7113 DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `platform_universal_history_offset` (
  `offset_type` tinyint(3) unsigned NOT NULL COMMENT '0: parent',
  `end_bin_log_file_name` varchar(255) NOT NULL COMMENT 'child를 위한 end_bin_log file_name',
  `end_bin_log_position` int(10) unsigned NOT NULL COMMENT 'child를 위한 end_bin_log position',
  `end_bin_log_date` datetime NOT NULL COMMENT 'end bin_log_position 날짜',
  PRIMARY KEY (`offset_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `platform_time_monitor` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '시퀀스',
  `type` varchar(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'time monitor 타입',
  `elapsed_time` mediumint(8) unsigned NOT NULL COMMENT '걸린시간 초 (최대194일)',
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일',
  PRIMARY KEY (`id`),
  KEY `type_reg_date_idx` (`type`,`reg_date`)
) ENGINE=InnoDB AUTO_INCREMENT=535 DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `platform_once_history_3_binlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `binlog_filename` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `gtid_end_pos` int(10) unsigned NOT NULL,
  `gtid` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `reg_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `binlog` (`binlog_filename`,`gtid_end_pos`,`reg_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS `platform_once_history_3_row` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `binlog_id` int(10) unsigned NOT NULL,
  `idx` tinyint(3) unsigned NOT NULL,
  `table_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `pk_ids` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `action` enum('write','update','delete') COLLATE utf8_unicode_ci NOT NULL,
  `reg_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `binlog` (`binlog_id`,`idx`,`table_name`,`pk_ids`),
  KEY `search` (`reg_date`,`table_name`,`pk_ids`),
  CONSTRAINT `fk_once_binlog` FOREIGN KEY (`binlog_id`) REFERENCES `platform_once_history_3_binlog` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS `platform_once_history_3_column` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `row_id` bigint(20) unsigned NOT NULL COMMENT '수정 row No',
  `column` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT '컬럼',
  `data_before` text COLLATE utf8_unicode_ci COMMENT '수정 전',
  `data_after` text COLLATE utf8_unicode_ci COMMENT '수정 후',
  PRIMARY KEY (`id`),
  UNIQUE KEY `row_column` (`row_id`,`column`),
  CONSTRAINT `once_row_id` FOREIGN KEY (`row_id`) REFERENCES `platform_once_history_3_row` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=79309 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS `platform_once_history_child_offset` (
  `child_index` int(10) NOT NULL AUTO_INCREMENT COMMENT '시퀀스',
  `current_bin_log_file_name` varchar(255) NOT NULL COMMENT '마지막으로 처리된 bin_log file_name',
  `current_bin_log_position` int(10) unsigned NOT NULL COMMENT '마지막으로 처리된 bin_log position',
  `end_bin_log_file_name` varchar(255) NOT NULL COMMENT 'end_bin_log file_name 까지 처리 가능',
  `end_bin_log_position` int(10) unsigned NOT NULL COMMENT 'end_bin_log position 까지 처리 가능',
  `current_bin_log_position_date` datetime NOT NULL COMMENT '현재 bin_log_position 날짜',
  PRIMARY KEY (`child_index`)
) ENGINE=InnoDB AUTO_INCREMENT=7113 DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `platform_once_history_offset` (
  `offset_type` tinyint(3) unsigned NOT NULL COMMENT '0: parent',
  `end_bin_log_file_name` varchar(255) NOT NULL COMMENT 'child를 위한 end_bin_log file_name',
  `end_bin_log_position` int(10) unsigned NOT NULL COMMENT 'child를 위한 end_bin_log position',
  `end_bin_log_date` datetime NOT NULL COMMENT 'end bin_log_position 날짜',
  PRIMARY KEY (`offset_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
