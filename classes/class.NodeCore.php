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
    
    private $_attributes;

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
        if ($attribute == "pk")
        {
            throw new Exception('Setting a value to pk is forbidden');
        }
        else
        {
            $this->_cache->populate(array($attribute => $value));
        }
        return;
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

    public function commit(array $attributes = array())
    {
        //TODO: move all this logic to the query class

        // Get the node type
        $type = $this->getType();

        // Determine the correct context
        if (is_string($attributes['context']))
        {
            // Use the passed context
            $attributes['context'] = MeshTools::GetContextPks($attributes['context']);
        }
        else if (!$attributes['context'])
        {
            if ($this->pk)
            {
                // Bypass context on existing nodes if not explicitly passed in
                unset($attributes['context']);
            }
            else
            {
                // For new nodes use the default context
                $attributes['context'] = (array)MeshTools::GetDefaultContextPk();

                if (empty($attributes['context']))      // for non-context setups
                {
                    unset($attributes['context']);
                }

            }
        }

        if (! isset($attributes['context']))
        {
            // Do nothing, bypass context updates
        }
        else if (1 == count($attributes['context']))
        {
            $attributes['context'] = $attributes['context'][0];
        }
        else        // @todo New nodes can only have one context
        {
            throw new Exception('Cannot create node with ambiguous context');
        }

        $this->_cache->populate($attributes);
        $commit_data = $this->_cache->getData();
        // Separate the attirbutes from the links
        foreach ($commit_data as $key => $value)
        {
            // Link Nodes if not attributes
            if (! $this->_isAttribute($key))
            {
                // Use only valid node types
                if (MeshTools::IsNodeType($key))
                {
                    // Copy the attribute to the links array
                    $links[$key] = $commit_data[$key];
                }
                else
                {
                    throw new Exception('Cannot link '.$type.' with '.$key.' because type '.$key.' doesn\'t exist.');
                }

                // Remove all non-attirbutes from the attributes array
                unset($commit_data[$key]);
            }
        }
        
        try
        {
            // First update/insert the attributes
            // If the node exists then peform update, otherwise insert
            if ($this->pk)
            {
                $dbc = new DatabaseConnection();

                $sql = "UPDATE $type SET ";

                foreach ($commit_data as $key => $sql_value)
                {
                    $sql .= " $key = ".$dbc->quote($sql_value).", ";                    
                }

                $sql    = rtrim($sql, ', ');
                $sql    .= " WHERE pk = $this->pk";
                $dbc->query($sql);
            }
            else
            {
                $dbc = new DatabaseConnection();

                // Clean the attributes
                foreach ($commit_data as $key => $value)
                {
                    $sql_data[$key] = $dbc->quote($value);
                }

                // Build the columns and values
                $columns    = implode(',', array_keys($sql_data));
                $values     = implode(',', $sql_data);
                $sql        = "INSERT INTO $type ($columns) VALUES ($values)";
                $dbc->query($sql);

                $this->_cache->populate(array('pk' => mysql_insert_id()));      // @todo MySQL-dependant!
            }

            // Now link the nodes
            if (count($links))
            {
                echo "Links\n";
                foreach ($links as $type => $nodes)
                {
                    echo "Linking...";
                    print_r($nodes->toArray());
                    echo $type;
                    //$this->_linkNodes($nodes, $type);
                }
            }

            $node = new Node($this->_callChain);
            return $node;
        }
        catch (Exception $e)
        {
            throw new Exception($e);
        }
    }

    /*
     * @todo Replace this with something more abstract (pseudo-attribute?)
     */
    public function getType()
    {
        return $this->_callChain->getType();
    }

    private function _isAttribute($attribute)
    {
        // TODO: Move this logic into the query class

        $type   = $this->getType();


        if (empty($this->_attributes))
        {
            $dbc    = new DatabaseConnection();
            $sql    = "SHOW COLUMNS FROM $type ";       // @todo Mysql specific
            $rows   = $dbc->query($sql);

            foreach ($rows as $r)
            {
                $this->_attributes[] = $r['Field'];
            }
        }

        return in_array($attribute, $this->_attributes);
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
