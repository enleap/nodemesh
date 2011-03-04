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

class CallChain
{
    public $_calls = array();
    
    
    /*
    public function __construct(array $calls = array())
    {
        foreach ($calls as $call)
        {
            if ($call instanceof Call)
            {
                $this->_calls[] = $call;
            }
            else
            {
                $type = is_object($call) ? get_class($call) : gettype($call);
                throw new Exception('Cannot construct CallChain with ' . $type);
            }
        }
        
        return;
    }
    */

    
    /**
     * Add a Call object to the CallChain
     * 
     * @param mixed $call           Either a Call object, or a table name (from which a Call object will be created)
     * @param array $plugins        (Optional) An array of plugins
     * 
     * @return void
     */
    public function push($call, array $plugins = array())
    {
        if (! ($call instanceof Call))
        {
            $table       = (string) $call;
            $call        = new Call();
            $call->table = $table;
        }
        
        foreach ($plugins as $plugin)
        {
            $call->apply($plugin);
        }

        $this->_calls[] = $call;

        return;
    }
    
    
    public function peek()
    {
        $count = count($this->_calls);
        return empty($this->_calls) ? null : $this->_calls[$count - 1];
    }
    
    
    public function getType()
    {
        foreach (array_reverse($this->_calls) as $call)
        {
            if (isset($call->table))
            {
                return $call->table;
            }
        }
        
        throw new Exception('Cluster has missing type');
    }
    
    
    public function __clone()
    {
        foreach ($this->_calls as $i => $call)
        {
//            static $x;
//            
//            echo '<hr/><h3>';
//            echo ++$x;
//            echo '</h3><pre>';
//            print_r($call);
//            echo '</pre>';
            
            $this->_calls[$i] = clone $call;
        }
        
        return;
    }
}
