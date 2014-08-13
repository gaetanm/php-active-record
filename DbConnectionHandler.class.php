<?php
/**
 * Copyright (C) 2014 Gaëtan Masson
 *
 * This file is part of CaPHPy.
 *
 * CaPHPy is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * CaPHPy is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CaPHPy.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace sys\core;

use \PDO;
use PDOException;
use sys\error\DbConnectionHandlerException;

/**
 * DbConnectionHandler.
 *
 * @author Gaëtan Masson <gaetanm@gmail.com>
 */
class DbConnectionHandler
{
    private static $pdo = null;

    /**
     * Returns an array to use it as parameter for the PDO instance constructor.
     *
     * @param string $dbInfoKey
     *
     * @return array
     *
     * @throws DbConnectionHandlerException
     */
    private static function parseDbInfo($dbInfoKey)
    {
        $dbInfo = Caphpy::$dbInfo;

        if ($dbInfoKey === null)
        {
            if (!array_key_exists('main', $dbInfo))
                throw new DbConnectionHandlerException('DbConnectionHandler error: missing database information key ('.$dbInfoKey.' given)');

            $dbInfo = $dbInfo['main'];
        }
        else
        {
            if (!array_key_exists($dbInfoKey, $dbInfo))
                throw new DbConnectionHandlerException('DbConnectionHandler error: missing database information key ('.$dbInfoKey.' given)');

            $dbInfo = $dbInfo[$dbInfoKey];
        }
        return [$dbInfo['driver'].':host='.$dbInfo['host'].';dbname='.$dbInfo['db'], $dbInfo['usr'],
            $dbInfo['pwd']];
    }

    /**
     * Creates a PDO instance.
     *
     * @param string $dbInfoKey
     *
     * @throws DbConnectionHandlerException
     * @throws PDOException
     */
    public static function createPDOInstance($dbInfoKey)
    {
        try
        {
            $dbInfo = self::parseDbInfo($dbInfoKey);

            self::$pdo = new PDO($dbInfo[0], $dbInfo[1], $dbInfo[2]);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(DbConnectionHandlerException $e)
        {
            throw $e;
        }
        catch(PDOException $e)
        {
            throw $e;
        }
    }

    /**
     * Returns the PDO instance.
     *
     * @return PDO $pdo
     */
    public static function getPDO()
    {
        if (self::$pdo === null) self::createPDOInstance(null);
        return self::$pdo;
    }
}
