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

require_once('class.HistoryCollectionIterator.php');
require_once('class.HistoryRecord.php');

class HistoryCollection implements IteratorAggregate
{
    private $items = array();
    private $count = 0;
    
    public function __construct($records)
    {
        foreach ($records as $record)
        {
            $r = new HistoryRecord($record); 
            
            $this->items[$this->count++] = $r;
        }
    }
    
    public function getIterator()
    {
        return new HistoryCollectionIterator($this->items);
    }
    
}


?>
