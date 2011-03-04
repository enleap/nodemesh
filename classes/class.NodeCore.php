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

/**
 * @todo Bad abstraction: this superclass knows its subclasses!
 * 
 * @author joel
 *
 */
abstract class NodeCore
{
    protected $_cache;

    protected $_callChain;
    
    
    /**
     * Constructor
     * 
     * @param mixed $type               Normally a node type, but may also be a CallChain object to use
     * @param ...                       If $type is a node type, node plugins may be passed as additional arguments
     * 
     * @return void
     */
    public function __construct($type)
    {
        if (! isset($type))
        {
            throw new Exception('Invalid type passed to ' . get_class($this) . ' constructor');
        }

        $this->_cache = new Cache();
        
        if ($type instanceof CallChain)
        {
            $this->_callChain = clone $type;
        }
        else
        {
            $plugins = func_get_args();
            array_shift($plugins);              // shift $type

            $this->_callChain = new CallChain();
            $this->_callChain->push($type, $plugins);
        }
        
        return;
    }

    public function __get($attribute)
    {
        if ('Me' == $attribute)
        {
            return $this->Me();
        }
        else
        {
            return $this->__call($attribute, array());
        }
    }

    public function __call($method, $params)
    {
        //var_dump("Getting $method from a ".get_class($this));

        if (! isset($this->_cache[$method]) || ! empty($params))        // always refetch when $params is not empty
        {
            if (MeshTools::IsNodeType($method))
            {
                $cluster = new Cluster($this->_callChain);
                $cluster->_callChain->push($method, $params);

                if (empty($params))        // only cache when $params is empty
                {
                    $this->_cache[$method] = $cluster;
                }

                return $cluster;
            }
            else
            {
                $this->_fetch();        // @todo: disable caching when $params is set
            }
        }

        if (! empty($params) && ! MeshTools::IsNodeType($method))
        {
            throw new Exception('Parameters cannot be supplied for field of a node');
        }

        return $this->_cache[$method];
    }

    public function __set($attribute, $value)
    {
        throw new Exception('Setting attributes of ' . get_class($this) . ' object is forbidden');
    }
    
    public function __isset($attribute)
    {
        $attribute = $this->$attribute;
        return isset($attribute);
    }
    
    public function __unset($attribute)
    {
        throw new Exception('Unsetting attributes of ' . get_class($this) . ' object is forbidden');
        
        return;
    }
    
    abstract protected function _fetch();

    public function commit(array $changes)
    {
        $query = new Query($this->_callChain);

        $this->_cache->populate($query->commit($changes));      // keep all SQL in the Query class

        return $this;
    }

    /*
     * @todo Replace this with something more abstract (pseudo-attribute?)
     */
    public function getType()
    {
        return $this->_callChain->getType();
    }

    public function Me()
    {
        $plugins = func_get_args();
        
        if (empty($plugins))
        {
            return $this;       // shortcut
        }
        
        $cluster = new Cluster($this->_callChain);
        
        $call = new Call();
        foreach ($plugins as $plugin)
        {
            $call->apply($plugin);
        }
        
        $cluster->_callChain->push($call, $plugins);        

        return $cluster;
    }
    
    public function jump($flag)
    {
        $plugins = func_get_args();
        array_shift($plugins);          // shift $flag
        
        $cluster = new Cluster($this->_callChain);
        
        $call        = new Call();
        $call->jump  = $flag;
        
        $cluster->_callChain->push($call, $plugins);
        
        return $cluster;
    }
    
    abstract public function toArray();
}
