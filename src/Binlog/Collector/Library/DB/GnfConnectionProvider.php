<?php
namespace Binlog\Collector\Library\DB;

use Gnf\db\base;
use Gnf\db\PDO;

/**
 * Class GnfConnectionProvider
 * @package Binlog\Collector\Library\DB
 */
class GnfConnectionProvider extends ConnectionProvider
{
    /**
     * @var base[]
     */
    private static $connection_pool = [];

    public static function getGnfConnection(string $group_name): base
    {
        if (!isset(self::$connection_pool[$group_name])) {
            self::$connection_pool[$group_name] = self::createGnfConnection($group_name);
        }

        return self::$connection_pool[$group_name];
    }

    public static function createGnfConnection(string $group_name): base
    {
        return new PDO(parent::getConnection($group_name));
    }

    public static function closeAllConnections(): void
    {
        parent::closeAllConnections();
    }
}
