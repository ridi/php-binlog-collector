echo "1. remove all docker "
./docker/all-docker-rm.sh
echo "2. run all docker"
./docker/all-docker-run.sh
sleep 12;
echo "3. start db-master";
docker exec -it db-master bash -c "mysql -uroot -p1234 < temp/my.master.sql"
sleep 4;
echo "4. start db-slave";
docker exec -it db-slave bash -c "mysql -uroot -p1234 < temp/my.slave.sql"
sleep 4;
echo "5. start db-slave-slave";
docker exec -it db-slave-slave bash -c "mysql -uroot -p1234 < temp/my.slave-slave.sql"
sleep 2;
echo "6. start db-master init sql";
docker exec -it db-master bash -c "mysql -uroot -p1234 < temp/my.master.init_test.sql"

