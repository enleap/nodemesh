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
 * This class contains administrative methods designed to help maintain the db
 */

class HistoryRecord
{
    public $pk1;
    
    public $type1;
    
    public $pk2;
    
    public $type2;
    
    public $node1;
    
    public $node2;
    
    public $action;
    
    public $trigger_pk;
    
    public $trigger_type;
    
    public $trigger_node;
    
    public $trigger_process;
    
    public $date;
    
    public function __construct($record)
    {
        $this->user_pk  = $record['user_pk'];
        $this->pk1      = $record['pk1'];
        $this->type1    = $record['type1'];
        $this->action   = $record['action'];
        $this->date     = $record['date'];
        $this->summary  = $record['summary'];
        
        
        // Create the nodes, may be virtual
        if (MeshTools::IsNodeType($this->type1))
        {
            $this->node1 = new Node($this->type1, $this->pk1);    
        }
        
        $this->user = new Node('users', $this->user_pk);    
    }
}


?>
