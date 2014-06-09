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

class ClusterIterator implements Iterator
{
    private $_position = 0;

    private $_cluster;

    public function __construct(Cluster $cluster)
    {
        $this->_cluster = $cluster;

        return;
    }

    public function current()            // mixed
    {
        if (count($this->_cluster) > 0)
        {
            return $this->_cluster[$this->_position];
        }
        else
        {
            return false;
        }
    }

    public function key()                // scalar
    {
        return $this->_position;
    }

    public function next()                // void
    {
        ++$this->_position;

        return;
    }

    public function rewind()               // void
    {
        $this->_position = 0;

        return;
    }

    public function valid()                // bool
    {
        return (count($this->_cluster) > $this->_position);
    }
}
