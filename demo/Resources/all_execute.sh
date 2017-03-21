#! /bin/bash
#php tools/generate_test_sql.php > mariadb/my.master.init_test.sql
./init_environment.sh
./test_execute.sh
./php test_result_validate.php
