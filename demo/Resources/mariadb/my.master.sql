USE mysql;
CREATE USER 'repl'@'%' IDENTIFIED BY '1234';
GRANT REPLICATION SLAVE ON *.* to 'repl';
GRANT REPLICATION CLIENT ON *.* to 'repl';
GRANT SELECT ON *.* to 'repl';
CREATE USER 'testUser'@'%' identified by 'testUser';
GRANT ALL PRIVILEGES ON *.* TO 'testUser'@'%';
FLUSH PRIVILEGES;
