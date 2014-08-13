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

use \PDOException;
use \PDO;
use PDOStatement;

/**
 * Model stands for the M of MVC.
 *
 * @author  Gaëtan Masson <gaetanmdev@gmail.com>
 */
class Model
{
    private static $pdo;
    private static $stmt = [];

    /**
     * Returns the lowercase name of the called class without the namespace.
     *
     * @return  string
     */
    private static function getTableName()
    {
        $class = get_called_class();
        $lowerClass = strtolower($class);
        $finalClass = explode('\\', $lowerClass);
        return end($finalClass);
    }

    /**
     * Gets the PDO instance of the DbConnectionHandler.
     *
     * @return PDO
     */
    private static function getPDO()
    {
        self::$pdo = DbConnectionHandler::getPDO();
        return self::$pdo;
    }

    /**
     * Create iteratively an object for each foreign key of the table.
     *
     * @param Model $object
     */
    private static function getFkAsObject($object)
    {
        if (isset($object->fk))
        {
            foreach($object->fk as $key => $value)
            {
                $table = $key;

                if (!isset($object->pk)) $id = 'id';
                else $id = $object->pk;

                $className = 'app\\model\\'.$value;

                if ($object->$table != null) $object->$table = $className::select("WHERE $id = ?", $object->$table);
                if (isset($object->$table->fk)) self::getFkAsObject($object->$table);
            }
        }
    }

    /**
     * Returns query result as an object based on the name of the called class.
     *
     * @param PDOStatement $q
     * @param bool         $getFkAsObject
     *
     * @return Model $object
     */
    private static function asObject($q, $getFkAsObject)
    {
        $object = $q->fetchObject(get_called_class());
        if ($getFkAsObject) self::getFkAsObject($object);
        return $object;
    }

    /**
     * Returns query result as an objects array based on the name of the called class.
     *
     * @param PDOStatement $q
     * @param bool         $getFkAsObject
     *
     * @return array $objects
     */
    private static function asObjectArray($q, $getFkAsObject)
    {
        $objects = $q->fetchAll(PDO::FETCH_CLASS, get_called_class());

        if ($getFkAsObject)
        {
            foreach ($objects as $object)
            {
                self::getFkAsObject($object);
            }
        }
        return $objects;
    }

    /**
     * @param string $inputQuery
     * @param $data
     *
     * @return array $param
     */
    private static function parseQuery($inputQuery, $data)
    {
        $param = [];

        if (strpos($inputQuery, '?') !== false)
        {
            if (isset($data))
            {
                if (is_array($data))
                {
                    foreach ($data as $value)
                    {
                        $param[] = $value;
                    }
                }

                else $param[] = $data;
            }
            else $param = null;
        }
        return $param;
    }

    /**
     * Parses input data and return the query and its parameters.
     *
     * @param array $data
     *
     * @return array
     */
    private static function parseUpdateData($data)
    {
        $size = count($data);
        $i = 0;
        $q = null;
        $param = null;

        foreach($data as $attribute => $value)
        {
            $i = ++$i;

            if (is_object($value))
            {
                if (!isset($value->pk)) $id = 'id';
                else $id = $value->pk;
                $value = $value->$id;
            }

            if ($i == $size) $att = $attribute.' = ?';
            else $att = $attribute.' = ?,';

            $q = $q.$att;

            $param[] = $value;
        }
        return [$q, $param];
    }

    /**
     * @param string $inputQuery
     * @param array  $data
     * @param bool   $getFkAsObject
     *
     * @return Model
     */
    public static function select($inputQuery, $data = null, $getFkAsObject = false)
    {
        $param = self::parseQuery($inputQuery, $data);
        $query = 'SELECT * FROM '.self::getTableName().' '.$inputQuery;
        try
        {
            if (!array_key_exists($query, self::$stmt))
            {
                self::$stmt[$query] = self::getPDO()->prepare($query);
            }
            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
        return self::asObject(self::$stmt[$query], $getFkAsObject);
    }

    /**
     * Returns an array containing the selected rows as objects.
     *
     * @param string $inputQuery
     * @param array  $data
     * @param bool   $getFkAsObject
     *
     * @return array
     */
    public static function selectAll($inputQuery = null, $data = null, $getFkAsObject = true)
    {
        if ($inputQuery != null)
        {
            $param = self::parseQuery($inputQuery, $data);
            $query = 'SELECT * FROM '.self::getTableName().' '.$inputQuery;
        }

        else
        {
            $query = 'SELECT * FROM '.self::getTableName();
            $param = null;
        }
        try
        {
            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);

            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
        return self::asObjectArray(self::$stmt[$query], $getFkAsObject);
    }

    /**
     * Deletes the actual instance of the database by using his id.
     */
    public function delete()
    {
        $data = get_object_vars($this);

        if (!isset($this->pk)) $id = 'id';
        else $id = $this->pk;

        $inputQuery = "WHERE $id = ?";
        $param = self::parseQuery($inputQuery, $data[$id]);
        $query = 'DELETE FROM '.self::getTableName().' '.$inputQuery;
        try
        {
            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);

            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
    }

    /**
     * Deletes all the table rows.
     *
     * @param string $inputQuery
     * @param array  $data
     */
    public static function deleteAll($inputQuery = null, $data = null)
    {
        try
        {
            if ($data != null)
            {
                $param = self::parseQuery($inputQuery, $data);

                $query = 'DELETE FROM '.self::getTableName().' '.$inputQuery;
            }

            else
            {
                $query = 'DELETE FROM '.self::getTableName();
                $param = null;
            }

            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);
            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
    }

    /**
     * Updates row(s).
     *
     * @param array  $attributes
     * @param string $inputQuery
     * @param array  $data
     */
    public static function updateAll($attributes, $inputQuery = null, $data = null)
    {
        $param = null;
        $attributes = self::parseUpdateData($attributes);

        if ($inputQuery != null)
        {
            $param = array_merge($attributes[1], self::parseQuery($inputQuery, $data));
            $query = 'UPDATE '.self::getTableName().' SET '.$attributes[0].' '.$inputQuery;
        }
        else $query = 'UPDATE '.self::getTableName().' SET '.$attributes[0];

        try
        {
            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);
            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
    }

    /**
     * Checks if a data exists in the database.
     *
     * @param string $inputQuery
     * @param array  $data
     *
     * @return boolean
     */
    public static function exists($inputQuery, $data)
    {
        $param = self::parseQuery($inputQuery, $data);
        $query = 'SELECT COUNT(*) FROM '.self::getTableName().' '.$inputQuery;

        try
        {
            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);
            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
        return (bool) self::$stmt[$query]->fetchColumn();
    }

    /**
     * Counts row number.
     *
     * @param string $inputQuery
     * @param array  $data
     *
     * @return int
     */
    public static function count($inputQuery = null, $data = null)
    {
        if ($inputQuery != null)
        {
            $param = self::parseQuery($inputQuery, $data);
            $query = 'SELECT COUNT(*) FROM '.self::getTableName().' '.$inputQuery;
        }
        else
        {
            $param = null;
            $query = 'SELECT COUNT(*) FROM '.self::getTableName();
        }
        try
        {
            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);
            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
        return self::$stmt[$query]->fetchColumn();
    }

    /**
     * Performs a custom query.
     *
     * @param string $inputQuery
     * @param array  $data
     *
     * @return PDOStatement
     */
    public static function doQuery($inputQuery, $data = null)
    {
        $param = self::parseQuery($inputQuery, $data);
        try
        {
            if (!array_key_exists($inputQuery, self::$stmt))
                self::$stmt[$inputQuery] = self::getPDO()->prepare($inputQuery);

            self::$stmt[$inputQuery]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
        return self::$stmt[$inputQuery];
    }

    /**
     * Inserts the data of the actual instance into the database and hydrates his id.
     *
     * @param bool $enableAI
     */
    public function insert($enableAI = true)
    {
        $data = get_object_vars($this);
        $param = null;

        if ($enableAI === true)
        {
            if (!isset($this->pk)) $id = 'id';
            else $id = $this->pk;
            unset($data[$id]);
        }

        unset($data['fk']);
        unset($data['pk']);

        foreach ($data as $key => $value)
        {
            if (is_object($value))
            {
                if (!isset($value->pk)) $id = 'id';
                else $id = $value->pk;

                $data[$key] = $value->$id;
            }
        }

        $attributes = implode(', ', array_keys($data));

        $size = count($data);
        $i = 0;
        $q = null;

        foreach($data as $attribute => $value)
        {
            $i = ++$i;
            if ($i == $size) $att = ' ?';
            else $att = ' ?,';

            $q = $q.$att;

            $param[] = $value;
        }

        $query = 'INSERT INTO '.self::getTableName().' ('.$attributes.') VALUES ('.$q.')';

        try
        {
            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);

            self::$stmt[$query]->execute($param);

            if ($enableAI === true) $this->$id = self::getPDO()->lastInsertId();
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
    }

    /**
     * Updates the actual instance of the database by using his properties.
     */
    public function update()
    {
        $data = get_object_vars($this);

        if (!isset($this->pk)) $id = 'id';
        else $id = $this->pk;

        unset($data['fk']);
        unset($data['pk']);

        $val = $data[$id];

        try
        {
            $data = self::parseUpdateData($data);

            $inputQuery = "WHERE $id = ?";
            $param = $val;

            $param = self::parseQuery($inputQuery, $param);
            $param = array_merge($data[1], $param);

            $query = 'UPDATE '.self::getTableName().' SET '.$data[0].' '.$inputQuery;

            if (!array_key_exists($query, self::$stmt))
                self::$stmt[$query] = self::getPDO()->prepare($query);

            self::$stmt[$query]->execute($param);
        }
        catch(PDOException $e)
        {
            ExceptionHandler::displayException($e);
        }
    }
}