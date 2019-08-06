# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

### CHANGED
- upgrade php-mysql-replication v6.0.1
  
## [1.1.1] - 2018-11-12
### CHANGED
- upgrade php-mysql-replication v5.0.5
-- fixed support to recive more than 16Mbyte + tests
- change mariadb's confs

## [1.1.0] - 2018-08-13

### CHANGED
- upgrade php-mysql-replication v5.0.4
-- configService -> ConfigFactory
-- mySQLReplicationFactory() -> MySQLReplicationFactory($config);
-- update using BinlogException
- refactoring based on php7.1
- binlogId's type string to int

## [1.0.3] - 2018-01-02

### Fixed
- fix setting of logger
- pass all test

## [1.0.2] - 2018-01-02

### Added
- add .travis.yml, LICENSE
- upload github

## [1.0.1] - 2017-12-21

### Changed
- remove unnecessary gtid converting in `BinlogHistoryGtidChildUpdater`
- Change tab to space

## [1.0.0]
