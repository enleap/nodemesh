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

class Column
{
    protected $_table;

    protected $_column;
    
    
    public function __construct($column)
    {
        list($this->_table, $this->_column) = $this->parse($column);
        
        return;
    }
    
    
    public function parse($column)
    {
        if (false === strpos($column, '.'))
        {
            return array(null, $column);
        }
        else
        {
            return explode('.', $column, 2);
        }
    }
    
    
    public function __toString()
    {
        return isset($this->_table) ? "{$this->_table}.{$this->_column}" : $this->_column;
    }
    
    
    public function toString($default_table = null)
    {
        if (isset($this->_table))
        {
            return (string) $this;
        }
        else
        {
            $this->_table = $default_table;
            $string       = (string) $this;
            $this->_table = null;
            
            return $string;
        }
    }
    
    
    public function getTable()
    {
        return $this->_table;
    }
    
    
    public function getColumn()
    {
        return $this->_column;
    }
    
    
    public function setTable($new_table)
    {
        $this->_table = $new_table;
    }
}
