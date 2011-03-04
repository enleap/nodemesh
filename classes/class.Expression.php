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

class Expression
{
    private $_columns = array();
    
    private $_expression;
    
    private $_alias;
    
    
    public function __construct($expression, $alias = null)
    {
        list($this->_expression, $columns) = $this->parse($expression);
        $this->_alias                      = $alias;
        
        foreach ($columns as $column)
        {
            $this->_columns[] = new Column($column);
        }
        
        return;
    }
    
    
    public function parse($expression)
    {
        // @todo Also matches non-symbolic operators
        // @todo Handle embedded strings (Note: hacks are in place to support %\w+ and \d\w*)
        static $column_pattern = '/(?<!%)((?:[[:alpha:]_]\w+\.)?\b[[:alpha:]_]\w+\b)(\s*)(?!\s|\()/';
        
        $columns = array();
        
        // @todo Return value may be boolean false on error
        if (preg_match_all($column_pattern, $expression, $columns, PREG_PATTERN_ORDER) > 0)
        {
            $columns = $columns[1];     // array of submatches
            
            $expression = str_replace('%', '%%', $expression);
            $expression = preg_replace($column_pattern, '%s\2', $expression);
        }
        else
        {
            $columns = array();
        }
        
        return array($expression, $columns);
    }
    
    
    public function __toString()
    {
        $columns = array();
        
        foreach ($this->_columns as $column)
        {
            $columns[] = (string) $column;
        }
        
        $columns[] = $this->getAlias();
        return vsprintf($this->_expression . ' AS %s', $columns);
    }
    
    
    public function toString($default_table = null, $hide_alias = false)
    {
        $columns = array();
        
        foreach ($this->_columns as $column)
        {
            $columns[] = $column->toString($default_table); 
        }
        
        if ($hide_alias)
        {
            return vsprintf($this->_expression, $columns);
        }
        else
        {
            $columns[] = $this->getAlias();
            return vsprintf($this->_expression . ' AS %s', $columns);
        }
    }
    
    
    public function transpose($new_table)
    {
        return $new_table . '.' . $this->getAlias() . ' AS ' . $this->getAlias();       // @todo Use proper escaping from DatabaseAdapter
    }
    
    
    public function getExpression()
    {
        return $this->_expression;
    }
    
    
    public function getColumns()
    {
        return $this->_columns;
    }
    
    
    public function getAlias()
    {
        if (isset($this->_alias))
        {
            return $this->_alias;
        }
        elseif (count($this->_columns) == 1)
        {
            return $this->_columns[0]->getColumn();
        }
        else
        {
            throw new Exception('Impossible to determine proper column name: ' . print_r($this, true));//(string) $this);
        }
    }
}
