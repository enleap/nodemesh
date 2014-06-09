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

class Cluster extends NodeCore implements ArrayAccess, Countable, IteratorAggregate
{
    private $_count;        // initialized as null, not zero

    public function getIterator()
    {
        return new ClusterIterator($this);
    }

    public function offsetGet($key)
    {
        //var_dump("Getting index $key");

        if (! isset($this->_count))
        {
            $this->_fetch();
        }

        return $this->_objectify($key);
    }

    private function _objectify($key)
    {
        if (! $this->_cache[$key] instanceof Node)
        {
            $node = new Node($this->_callChain);

            $call           = new Call();
            $call->limit    = 1;
            $call->start    = intval($key);                             // @todo Allow for string keys (new feature)
            $call->context  = $this->_callChain->peek()->context;       // peek() should always return a valid Call

            $node->_callChain->push($call);

            $node->_cache->populate((array) $this->_cache[$key]);

            $this->_cache[$key] = $node;
        }

        return $this->_cache[$key];
    }

    public function offsetSet($key, $value)
    {

    }

    public function offsetExists($key)
    {

    }

    public function offsetUnset($key)
    {

    }

    /*
     * @todo Don't query all the data on count (which might be too intensive), just query the count
     * @todo Better yet, look into pulling all the data during the query, but only if count is small
     */
    public function count()
    {
        if (! isset($this->_count))
        {
            $this->_fetch();
        }

        return count($this->_cache);
    }

    protected function _fetch()
    {
        $query  = new Query($this->_callChain);

        try
        {
            $this->_cache->populate($query->execute());
            $this->_count = count($this->_cache);
        }
        catch (Exception $e)
        {
            throw $e;
        }

        return;
    }

    public function populate($type, $sql)
    {
        try
        {
            $dbc    = new DatabaseConnection();
            $rows   = $dbc->query($sql);

            $pks = array();
            foreach ($rows as $row)
            {
                $pks[] = $row['pk'];
            }

            if (count($pks))
            {
                $pks = implode(',', $pks);
                $plugin = "in:pk:$pks";
            }
            else
            {
                $plugin = 'in:pk:0';
            }

            $this->_callChain = new CallChain();
            $this->_callChain->push($type, array($plugin));

            $this->_cache->populate($rows);
            $this->_count = count($this->_cache);
        }
        catch (Exception $e)
        {
            throw $e;
        }
    }

    public function toArray()
    {
        $data = array();

        foreach ($this as $node)
        {
            $data[] = $node->toArray();
        }

        return $data;
    }
}
