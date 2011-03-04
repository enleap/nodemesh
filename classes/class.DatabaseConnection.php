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

class DatabaseConnection //implements DatabaseAdapterInterface
{
    private $_resource;
    private $_dba;

    public function __construct()
    {
        $config = Config::GetConfig();
        
        $this->_dba = DatabaseAdapter::Create($config['db_adapter']);
        $this->_dba->connect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_flags']);

        // Connect to the default database
        // @todo Move this decision out of this class
        if (class_exists('Bootstrap', false))
        {
            $this->_dba->selectDatabase(Bootstrap::GetDefaultDatabase());       // @todo Abstraction violation!
        }
        else
        {
            $default_db = Config::GetValue('default_db');
            
            if (isset($default_db))
            {
                $this->_dba->selectDatabase($default_db);                       // @todo Hack!
            }
        }
        
        return;
    }

    public function __call($method, $params)
    {
        return call_user_func_array(array($this->_dba, $method), $params);
    }

    public function escape($value)
    {
        return isset($value) ? $this->_dba->escape($value) : 'NULL';
    }
}
