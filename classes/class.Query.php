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

class Query
{
    private $_sql;

    private static $_ContextTables;

    private static $_JoinTableProperties;

    private $_jumpFlags = array();

    public function __construct(CallChain $callChain)
    {
//        echo '<pre>'; print_r($callChain); echo '</pre>';

        $table          = null;
        $sql            = null;
        $jump_flags     = array();
        $extra_columns  = array();

        foreach ($callChain->_calls as $i => $call)
        {
            $wrapped = @isset($callChain->_calls[$i + 1]);      // @todo Do we have to look ahead like this?

            list($table, $sql, $extra_columns) = $this->_process($call, $table, $sql, $jump_flags, $extra_columns, $wrapped);

            if ($call->flag)
            {
                $jump_flags[$call->flag] = $table;
            }
        }

        $this->_sql = $sql;
    }


    private function _process(Call $call, $prev_table, $sql, &$jump_flags, $extra_columns, $wrapped)
    {
        /* table */

        if (isset($call->table))
        {
            $table = $call->table;
        }
        elseif (isset($call->jump))
        {
            $table = $jump_flags[$call->jump];
            unset($jump_flags[$call->jump]);
        }
        else
        {
            $table = $prev_table;
        }


        /* columns */

        // Rule: every column (except *) should follow this format: TABLE.COLUMN AS ALIAS

        $columns = array();                         // @todo Use the terms 'Column' and 'Expression' consistently

        if (empty($call->columns) && ! $wrapped)
        {
            $columns[] = $table . '.*';
        }
        else
        {
            foreach ($call->columns as $column)
            {
                // @todo Clean up this working code; check for columns that are built from others in $extra_columns

                foreach ($column->getColumns() as $real_column)
                {
                    $real_table = $real_column->getTable();
                    if (! isset($real_table))
                    {
                        foreach ($extra_columns as $extra)
                        {
                            if ($real_column->getColumn() == $extra->getAlias())
                            {
                                $columns[] = $column->toString('link');
                                continue 3;
                            }
                        }
                    }
                }

                $columns[] = $column->toString($table);
            }

            $columns[] = $table . '.pk AS pk';                  // @todo Use proper escaping from DatabaseAdapter
        }

        foreach ($jump_flags as $flag => $flag_table)
        {
            $columns[] = "link.`#$flag` AS `#$flag`";           // @todo Use proper escaping from DatabaseAdapter
        }

        if (isset($call->flag))
        {
            $columns[] = "$table.pk AS `#{$call->flag}`";       // @todo Use proper escaping from DatabaseAdapter
        }

        foreach ($extra_columns as $col)
        {
            $columns[] = $col->transpose('link');               // @todo Don't hardcode 'link' here
        }

        $columns = join(', ', array_unique($columns));          // @todo Replace this with a unique alias check


        /* where */

        // @todo If possible, every column should follow this format: TABLE.COLUMN

        $where   = $this->_format('WHERE ( %s )', join(' ', $this->_formatWhere($call->where, $call->columns)));

        // Handle context filtering
        if (isset($call->context) && $this->_isContextSupported($table))
        {
            $contexts  = join(',', MeshTools::GetContextPks($call->context));
            $where     = strlen($where) > 0     ? "$where AND $table.context IN ($contexts)"
                                                : "WHERE $table.context IN ($contexts)";
        }


        /* group */

        // Any aliases must be unqualified, so we are not qualifying anything

        $group   = $this->_format('GROUP BY %s', join (', ', $this->_formatGroup($call->group)));


        /* order */

        // Any aliases must be unqualified, so we are not qualifying anything

        $order   = $this->_format('ORDER BY %s', join (', ', $this->_formatOrder($call->order)));


        /* limit */

        // LIMIT 0, 5 = LIMIT 5 so this should not be a problem as $start defaults to 0
        $limit = $this->_formatLimit('LIMIT %d, %d', $call->start, $call->limit);


        /* join */

        if (isset($sql))
        {
            if ($call->not_linked)
            {
                if ($call->optional)
                {
                    throw new Exception('OPTIONAL and NOT_LINKED are incompatible');
                }

                $outer_join_type    = 'LEFT';
                $inner_join_type    = '';
                $where              = ('' == $where) ? "WHERE link.fk IS NULL" : "$where AND link.fk IS NULL";
            }
            elseif ($call->optional)
            {
                $outer_join_type    = 'RIGHT';
                $inner_join_type    = 'RIGHT';
            }
            else
            {
                $outer_join_type    = '';
                $inner_join_type    = '';
            }

            $lookup  = $this->_formatJoin($call->table, $prev_table, $sql, $call->wrap, $call->jump, array_keys($jump_flags), $extra_columns, $inner_join_type);
            $join    = $this->_format("%s JOIN (\n\n%s\n\n    ) AS link\n    ON %s.pk = link.fk",
                                            $outer_join_type, $lookup, $table);
        }
        else
        {
            $join = '';
        }


        /* create sql */

        $sql = "SELECT DISTINCT $columns\nFROM $table\n"
             . $this->_format("    %s\n", $join)
             . $this->_format("%s\n", $where)
             . $this->_format("%s\n", $group)
             . $this->_format("%s\n", $order)
             . $this->_format("%s\n", $limit);


        /* debug and return */

        if ($call->debug)
        {
            MeshTools::$DebugCounter++;
            echo 'Q' . MeshTools::$DebugCounter . '==========>' . $sql;
            error_log('Q' . MeshTools::$DebugCounter . '==========>' . $sql);
        }

        return array($table, $sql, array_merge($extra_columns, $call->columns));
    }

    public function getDebugCount()
    {
        return self::$_debug_count;
    }

    /*
     * @todo Merge the caching portion of this function with _getLinkProperties()
     */
    private function _isContextSupported($table)
    {
        if (! isset(self::$_ContextTables))
        {
            $query = "SELECT TABLE_NAME
                      FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE COLUMN_NAME = 'context'";
            $result = mysql_query($query);

            self::$_ContextTables = array();        // prevent isset() test above from occurring again

            while ($row = mysql_fetch_assoc($result))
            {
                self::$_ContextTables[$row['TABLE_NAME']] = $row['TABLE_NAME'];
            }
        }

        return array_key_exists($table, self::$_ContextTables);
    }


    private function _indent($string)
    {
        $lines = explode("\n", $string);
        foreach ($lines as &$line)
        {
            $line = '        ' . $line;
        }

        return join("\n", $lines);
    }


    private function _formatJoin($outerTable, $innerTable, $innerSql, $wrap, $jump_flag, $jump_flags, $extra_columns, $inner_join_type)
    {
        $innerSql = $this->_indent($innerSql);

        // @todo How about turning all these columns into Column objects?

        if (isset($jump_flag))
        {
            $innerColumn = '`#' . $jump_flag . '`';
        }
        else
        {
            $innerColumn = 'pk';
        }

        $column_list = array();
        foreach ($jump_flags as $jump)
        {
            $column_list[] = "$innerTable.`#$jump` AS `#$jump`";
        }

        foreach ($extra_columns as $column)
        {
            $column_list[] = $column->transpose($innerTable);
        }

        $column_list = empty($column_list) ? '' : (', ' . join(', ', $column_list));

        if (! isset($outerTable) || $wrap || isset($jump_flag))
        {
            // this is probably inefficient
            $sql = "SELECT $innerTable.$innerColumn AS fk $column_list\n"
                 . "FROM (\n\n$innerSql\n\n"
                 . ") AS $innerTable";
        }
        elseif (isset($innerTable))     // exactly the same as: isset($innerSql)
        {
            list($joinTable, $outerColumn, $innerColumn, $link_properties_sql)
                                                                    = $this->_getJoinDetails($outerTable, $innerTable);

            if (! isset($joinTable))
            {
                throw new Exception("Links between $outerTable and $innerTable are not allowed");
            }

            $sql = "SELECT `$joinTable`.$outerColumn AS fk $link_properties_sql $column_list\n"
                 . "FROM `$joinTable`\n"
                 . "    $inner_join_type JOIN (\n\n$innerSql\n\n"
                 . "    ) AS $innerTable\n"
                 . "    ON $innerTable.pk = `$joinTable`.$innerColumn";
        }
        else
        {
            $sql = "";
        }

        return $this->_indent($sql);
    }

    /*
     * Putting this in a separate method allows us to abstract it out later
     */
    private function _getJoinDetails($outerTable, $innerTable)
    {
        if ($outerTable < $innerTable)
        {
            $joinTable   = "$outerTable#$innerTable";
            $outerColumn = 'pk1';
            $innerColumn = 'pk2';
        }
        else
        {
            $joinTable   = "$innerTable#$outerTable";
            $outerColumn = 'pk2';
            $innerColumn = 'pk1';
        }

        $link_properties = $this->_getLinkProperties($joinTable);
        $sql             = '';


        foreach ($link_properties as $property)
        {
            if ('direction' == $property)
            {
                list($forward, $reverse) = ('pk1' == $innerColumn) ? array('LTR', 'RTL') : array('RTL', 'LTR');

                $sql .= ", (CASE WHEN direction = '$forward' THEN 'forward' WHEN direction = '$reverse' THEN 'reverse' ELSE NULL END) AS direction";
            }
            else
            {
                $sql .= ", `$joinTable`.$property AS $property";
            }
        }

        return array($joinTable, $outerColumn, $innerColumn, $sql);
    }

    private function _getLinkProperties($joinTable)
    {
        if (! isset(self::$_JoinTableProperties))
        {
            $query = "SELECT TABLE_NAME, COLUMN_NAME
                      FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_NAME LIKE '%#%' AND COLUMN_NAME != 'pk1' AND COLUMN_NAME != 'pk2'";
            $result = mysql_query($query);

            self::$_JoinTableProperties = array();        // prevent isset() test above from occurring again

            while ($row = mysql_fetch_assoc($result))
            {
                $table  = $row['TABLE_NAME'];
                $column = $row['COLUMN_NAME'];
                self::$_JoinTableProperties[$table][$column] = $column;
            }
        }

        return self::$_JoinTableProperties[$joinTable];
    }

    private function _formatWhere($where, $columns)
    {
        $conditions = array();

        $count = 0;
        foreach ($where as $w)
        {
            // Check to see if its a nested where clause
            if ($w[' OR '] || $w[' AND '])
            {
                // Skip the first andor
                if ($count)
                {
                    $andor = $w[' OR '] ? ' OR ' : ' AND ';
                }

                $conditions[] = " $andor ( ";

                // Data is three levels deep
                foreach ($w as $w2)
                {
                    $count2 = 0;
                    foreach ($w2 as $w3)
                    {
                        // Skip the first andor
                        if ($count2)
                        {
                            $andor = $w3['andor'];
                        }
                        else
                        {
                            $andor = '';
                        }
                        $conditions[] = $this->_getWhereCondition($w3, $andor, $columns);

                        $count2++;
                    }
                }

                $conditions[] = ' ) ';
            }
            else
            {
                // Skip the first andor
                if ($count)
                {
                    $andor = $w['andor'];
                }
                $conditions[] = $this->_getWhereCondition($w, $andor, $columns);
            }

            $count++;
        }

        return $conditions;
    }

    // @todo Columns passed in here to replace aliases with underlying columns/expressions; is better flow possible?
    private function _getWhereCondition($w, $andor, $columns)
    {
        $column = $w['column']->toString(null, true);

        // quick check to see whether any recent aliases are being used here
        foreach ($columns as $col)
        {
            if ($column == $col->getAlias())
            {
                $column = '(' . $col->toString(null, true) . ')';       // wrap it in parens to avoid any problems
                break;
            }
        }

        $condition    = str_replace(array('%col', '%val'), array($column, $w['value']), $w['query']);
        return "$andor $condition";
    }

    private function _formatOrder($order)
    {
        $conditions = array();

        foreach ($order as $o)
        {
            $column         = $o['column']->toString(null, true);       // @todo Check aliases, then columns
            $condition      = str_replace(array('%col', '%val'), array($column, $o['value']), $o['query']);
            $conditions[]   = $condition;
        }

        return $conditions;
    }

    private function _formatGroup($group)
    {
        $conditions = array();

        foreach ($group as $g)
        {
            $column         = $g['column']->toString(null, true);       // @todo Check columns, then aliases
            $condition      = str_replace(array('%col', '%val'), array($column, $g['value']), $g['query']);
            $conditions[]   = $condition;
        }

        return $conditions;
    }

    private function _formatLimit($format, $start, $limit)
    {
        if ($limit)
        {
            return sprintf($format, $start, $limit);
        }
        else
        {
            return '';
        }
    }

    private function _format($format)
    {
        $args = func_get_args();

        if (count(array_filter($args)) > 1)     // if more than one argument is set (first argument will always be set)
        {
            return call_user_func_array('sprintf', $args);
        }
        else
        {
            return '';
        }
    }

    public function execute()
    {
        // @todo Select the proper database from Config

        if (MeshTools::$GlobalDebug)
        {
            MeshTools::$DebugCounter++;
            echo 'Q' . MeshTools::$DebugCounter . '==========>' . $this->_sql;
            error_log('Q' . MeshTools::$DebugCounter . '==========>' . $this->_sql);
        }

        $dbc = new DatabaseConnection();
        return $dbc->query($this->_sql);
    }

    public function commit($changes)
    {
        $table = $this->_getTable();
        $pk    = $this->_getPk();

        if ($pk && empty($changes))     // nothing to update
        {
            return array();
        }
        elseif ($pk)        // update
        {
            $updates = array();

            foreach ($changes as $col => $v)
            {
                // @todo Use database-specific quoting and escaping
                $updates[] = "`$col` = " . '"' . mysql_escape_string($v) . '"';
            }

            $updates = join(', ', $updates);

            $this->_sql = "UPDATE $table SET $updates WHERE pk = $pk";
        }
        elseif (empty($changes))        // simple insert
        {
            $this->_sql = "INSERT INTO $table (pk) VALUES (NULL); SELECT LAST_INSERT_ID()";     // @todo Not portable!
        }
        else        // full insert
        {
            $columns = array();
            $values  = array();

            foreach ($changes as $col => $v)
            {
                $columns[] = "`$col`";                                  // @todo Use database-specific quoting
                $values[]  = '"' . mysql_escape_string($v) . '"';       // @todo Use database-specific escaping
            }

            $columns = join(', ', $columns);
            $values  = join(', ', $values);

            $this->_sql = "INSERT INTO $table ($columns) VALUES ($values); SELECT LAST_INSERT_ID()";    // @todo Not portable!
        }

        return $this->execute();
    }

    private function _getTable()
    {

    }

    private function _getPk()
    {

    }
}
