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

class Call
{
    // SQL based attributes
    
    public $columns = array();

    public $table;

    public $where = array();

    public $group = array();

    public $order = array();

    public $start = 0;

    public $limit;
    
    
    // API special attributes
    
    public $context;
    
    public $debug = false;

    public $flag;
    
    public $jump;
    
    public $not_linked = false;
    
    public $wrap = false;       // @todo Evaluate whether this is really needed
    
    public $optional = false;
    
    
    public function __construct()
    {
        // Set the default context if available
        $this->context = MeshTools::GetContext();
    }
    
    
    /*
    public function apply($plugin)
    {
        $plugin_handler = Config::GetValue('plugin_handler');
        
        // parse out plugin name, optional column, and optional value
        preg_match('/^(\w+)(?::(\w+))?:(.*)$/', $plugin, $pieces);
        
        array_shift($pieces);               // remove first element (entire matching string)
        array_unshift($pieces, $this);      // prepend current Call
       
        $status = call_user_func_array(array($plugin_handler, 'ApplyPlugin'), $pieces);
        
        if (! $status)
        {
            throw new Exception('Unsupported plugin ' . $pieces[1]);
        }
    }
    */
    
    
    /*
     * @todo Allow filters to be defined and registered outside of this class
     */
    public function apply($filter)
    {
        // Group and apply the where clause fitlers
        if (is_array($filter))
        {
            // Check to see if andor for this block is specified
            if ($filter['or'] || $filter['and'])
            {
                if ($filter['or'])
                {
                    $andor = ' OR ';
                }
                else
                {
                    $andor = ' AND ';
                }

                // Strip the wrapping array
                foreach ($filter as $f)
                {
                    foreach ($f as $f2)
                    {
                        $wheres[] = $this->_apply($f2);
                    }
                }
            }
            else
            {
                foreach ($filter as $f)
                {
                    $andor      = ' AND ';
                    $wheres[]   = $this->_apply($f);
                }
            }

            $this->where[] = array($andor => $wheres);
        }
        else
        {
            // Anything returned is a where clause, otherwise its already applied
            $where = $this->_apply($filter);

            // Apply there where, otherwise return
            if ($where)
            {
                $this->where[] = $where;
            }
        }

        return;
    }

    /**
     * applies non-where clause filters immediately and returns where clause filters for further processing
     * @param $filter
     * @return unknown_type
     */
    private function _apply($filter)
    {
        if (is_numeric($filter))        // just a pk
        {
            return array(
                                'column' => new Expression('pk'),
                                'value'  => intval($filter),
                                'query'  => '%col = %val',
                            );
        }

        // break out the filter from its attributes and values
        $filter = explode(':', $filter);

        // ##############################################################################
        // Check for modifiers and remove them in succession

        // Check for andor modifier
        if ('or' == $filter[0])// || 'and' == $filter[0])
        {
            $andor = ' ' . strtoupper(array_shift($filter)) . ' ';
        }
        else
        {
            $andor = ' AND ';
        }
        
        /*
        // Check for wrap modifier - there has to be a better name for this but time is short
        if ('wrap' == $filter[0])
        {
            // Remove the wrap filter
            array_shift($filter);

            // Get the expression
            $expression = array_shift($filter);

            // Get the attribute and value
            $expression = explode('>>', $expression);
            $attribute  = $expression[0];
            $value      = $expression[1];

            // wrap the attribute with value
            $column_clause = str_replace('['.$attribute.']', '%col', $value);
        }
        else
        {
        */
            $column_clause = '%col';
        //}

        // Check for alias modifier
        if ('alias' == $filter[0])
        {
            // Remove the alias filter
            array_shift($filter);

//            $alias = true;
        }

        // End modifiers
        // ##############################################################################

        // Check for allowed filter formats 'filter', 'filter:value', 'filter:attribute:value'
        switch (count($filter))
        {
            case 3:
                $filter_name    = $filter[0];
                $attribute      = $filter[1];
                $value          = $filter[2];
                break;

            case 2:
                $filter_name    = $filter[0];
                $value          = $filter[1];
                break;

            case 1:
                $filter_name    = $filter[0];
                break;
        }

        // Wrap non-numeric values in quotes
        if (is_numeric($value))
        {
            $value_clause = '%val';
        }
        else
        {
            $value_clause = "'%val'";
        }

        switch ($filter_name)
        {
            // Alphabetical list of non-where clause filters
            // All these filters should apply and return

            case 'context':
                if ('*' == $value)
                {
                    // Bypass context system entirely
                    $this->context = null;
                }
                else
                {
                    $this->context = $value;
                }
                return;

            case 'debug':
                $this->debug = true;
                return;

            case 'group':
                $query = $alias ? '%val' : '%col';

                $this->group[] = array(
                                    'column'    => new Expression($value),
                                    'value'     => $value,
                                    'query'     => $query,
                                );
                return;

            case 'not_linked':
                $this->not_linked = true;
                return;

            case 'not_linked_to':
                $this->not_linked_to = true;
                return;
                
            case 'null':
                // Do nothing
                return;

            case 'optional':
                $this->optional = true;
                return;
                
            case 'order':
                // Check to see if ASC was ommitted
                if (!$filter[2])
                {
                    $attribute  = $filter[1];
                    $value      = 'ASC';
                }

                $query = $alias ? "$attribute %val" : '%col %val';

                $this->order[] = array(
                                    'column'    => new Expression($attribute),
                                    'value'     => strtoupper($value),
                                    'query'     => $query,
                                );
                return;

            case 'select':
                // Get each column/epxression
                $values = explode(';', $value);
                
                // Force selection of pk
                //$this->columns[] = new Column($this->table, 'pk');        // @todo Don't handle this here

                foreach ($values as $value)
                {
                    list($column, $alias) = explode('>>', trim($value), 2);
                    
                    $this->columns[] = new Expression($column, $alias);
                }
                return;
            
            case 'set':
                $this->flag = mysql_escape_string($value);
                $this->wrap = true;
                return;
                
            case 'start':
                $this->start = intval($value);
                return;

            case 'top':
                $this->limit = intval($value);
                return;


            // Alphabetical list of where clause filters
            // All these filters should set $query

            case 'between':
                $value  = explode(',', $value);
                $query  = "$column_clause BETWEEN ".mysql_escape_string($value[0]).' AND '.mysql_escape_string($value[1]);
                break;
            
            case 'dir':
                if ('forward' != strtolower($value) && 'reverse' != strtolower($value))
                {
                    throw new Exception('Invalid direction ' . $value . ' in dir: plugin');
                }
                $query = "link.direction = '" . mysql_escape_string($value) . "'";
                break;
                
            case 'eq':
                $value = mysql_escape_string($value);
                $query = "$column_clause = $value_clause";
                break;

            case 'gt':
                $value = mysql_escape_string($value);
                $query = "$column_clause > $value_clause";
                break;

            case 'gteq':
                $value = mysql_escape_string($value);
                $query = "$column_clause >= $value_clause";
                break;

            case 'in':
                // Convert to array
                $value = explode(',', $value);

                // Escape each item
                array_walk($value, array('Call', '_escapeString'));

                // Rebuild the string in quotes
                $value = "'" . implode('\',\'', $value) . "'";
                $query = "$column_clause IN (%val)";
                break;

            case 'is_null':
                $query      = "$column_clause IS NULL";
                $attribute  = $value;                       // $attribute must be a column! @todo: change this?
                break;
                
            case 'like':
                $value = mysql_escape_string($value);
                $query = "$column_clause LIKE '%val'";
                break;

            case 'lt':
                $value = mysql_escape_string($value);
                $query = "$column_clause < $value_clause";
                break;

            case 'lteq':
                $value = mysql_escape_string($value);
                $query = "$column_clause <= $value_clause";
                break;

            case 'ne':
                $value = mysql_escape_string($value);
                $query = "$column_clause != $value_clause";
                break;

            case 'ni':
                // Convert to array
                $value = explode(',', $value);

                // Escape each item
                array_walk($value, array('Call', '_escapeString'));

                // Rebuild the string in quotes
                $value = "'" . implode('\',\'', $value) . "'";
                $query = "$column_clause NOT IN (%val)";
                break;
                
            case 'not_null':
                $query      = "$column_clause IS NOT NULL";
                $attribute  = $value;                       // $attribute must be a column! @todo: change this?
                break;

            case 'rel':
                $query = "link.label = '" . mysql_escape_string($value) . "'";
                break;

            case 'virtual':
                // prevents the db from returning nodes
                $query = '1 != 1';
                break;

            default:
                throw new Exception('Unknown filter '. $filter_name);
                break;
        }

        return $this->_buildWhereArray($andor, $attribute, $value, $query, $alias);
    }
    
    
    private function _buildWhereArray($andor, $attribute, $value, $query, $alias)
    {
        // If alias then replace the column with the alias
        if ($alias)
        {
            $query = str_replace('%col', $attribute, $query);
        }

        return array(
                        'andor'  => $andor,
                        'column' => new Expression($attribute, $alias),
                        'value'  => $value,
                        'query'  => $query,
                    );
    }
    
    
    private function _escapeString(&$value)
    {
        $value = mysql_escape_string($value);
    }
}
