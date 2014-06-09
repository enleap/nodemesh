<?
/*
 *  Copyright 2010 Enleap, LLC
 *
 *  This file is part of the Node Mesh.
 *
 *  The Node Mesh is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The Node Mesh is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with the Node Mesh.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Assume MySQL version 5.1
 */
class MySqlAdapter implements DatabaseAdapterInterface
{
    private $_resource;

    public function connect($host, $user, $pass, $flags = null)
    {
        ob_start();
        $this->_resource = mysql_pconnect($host, $user, $pass);
        ob_end_clean();

        if (! is_resource($this->_resource))
        {
            throw new Exception('Database connection error: ' . mysql_error());
        }

        return;
    }

    public function selectDatabase($db)
    {
        if (! @mysql_select_db($db))
        {
            throw new Exception('Database could not be selected: ' . mysql_error());
        }

        return;
    }

    public function getDatabase()
    {
        $db = $this->query('SELECT DATABASE()');
        return array_pop($db[0]);
    }

    /**
     * Converts a SINGLE dimensional array into an object
     * @param array $array
     * @return stdClass
     */
    private function _objectify(array $array)
    {
        if (!is_array($array))
        {
            throw new Exception("_objectify expects paramter 1 to be an array");
        }

        $object = new stdClass();

        if (count($array) > 0)
        {
            foreach ($array as $key => $value)
            {
                $key = strtolower(trim($key));

                if (!empty($key))
                {
                    $object->$key = $value;
                }
            }
        }

        return $object;
    }

    public function query($sql, $objectify = false)
    {
        //var_dump($sql);
        $result = @mysql_query($sql);

        if (is_resource($result))
        {
            $rows = array();

            while ($row = mysql_fetch_assoc($result))
            {
                $row    = $objectify ? $this->_objectify($row) : $row;
                $rows[] = $row;
            }

            return $rows;
        }
        elseif (true === $result)
        {
            return true;        // or should we return array() ???
        }
        else
        {
            throw new Exception('Database query failed: ' . mysql_error() . "\nQuery: " . $sql);
        }
    }

    public function escape($value)
    {
        return mysql_real_escape_string($value, $this->_resource);
    }
}
