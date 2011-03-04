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

class Cache implements ArrayAccess, Countable
{
    private $_data = array();

    /*
     * @todo Allow for variable life span (like JSP variable scope)
     *          - page
     *          - request
     *          - session
     *          - application
     */

    public function __construct(array $data = array())
    {
        $this->populate($data, true);
    }

    public function count()
    {
        return count($this->_data);
    }

    public function offsetGet($key)
    {
        return @$this->_data[$key];
    }

    public function offsetSet($key, $value)
    {
        // always cache the new value, even if it is null
        return ($this->_data[$key] = $value);
    }

    public function offsetExists($key)
    {
        return array_key_exists($key, $this->_data);
    }

    public function offsetUnset($key)
    {
        unset($this->_data[$key]);
    }

    public function populate(array $data, $reset = false)
    {
        if ($reset)
        {
            $this->_data = $data;
        }
        else
        {
            foreach ($data as $k => $v)
            {
                $this->_data[$k] = $v;
            }
        }
    }

    public function getData()
    {
        return $this->_data;
    }
}
