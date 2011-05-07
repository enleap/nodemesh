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
 * This class contains sql helper methods designed to help build complex queries
 * to obtain data the traditional api can't obtain
 */

class MeshQuery
{

    private $_select = array();

    private $_from = array();

    private $_joins = array();

    private $_where;

    private $_order;

    private $_limit;

    private $_tables = array();

    private $_required_tables = array();

    private $_optional_tables = array();

    private $_aliases = array();

    private $_join_aliases = array();

    private $_context_pks;

    private $_sql;

    public function __construct()
    {
        // Figure out context
        $this->_context_pks = implode(',', MeshTools::GetContextPks());
    }

    public static function Create()
    {
        return new MeshQuery();
    }

    public function type($type, $select = null)
    {
        $this->_setType($type);

        if ($select)
        {
            $select = $this->_parseSelect($select);
            $this->_select($select);
        }

        return $this;
    }

    private function _isSelect($sql)
    {
        if ('select:' == substr($sql, 0, 7))
        {
            return true;
        }

        return false;
    }

    private function _parseSelect($sql)
    {
        // Strip off the 'select:'
        $sql = substr($sql, 7);

        // Replace all the '>>' with AS
        $sql = str_replace('>>', ' AS ', $sql);

        // Replace all ';' with commas
        $sql = str_replace(';', ',', $sql);

        return $sql;
    }

    private function _select($sql)
    {
        $this->_select[] = $sql;

        return $this;
    }

    public function join($type1, $type2 = null, $select = null)
    {
        if ($this->_isSelect($type2))
        {
            $select = $this->_parseSelect($type2);
            $this->_select($select);
            $this->_setJoin($type1, null, false, $select);
        }
        else if ($this->_isSelect($select))
        {
            $select = $this->_parseSelect($select);
            $this->_select($select);
            $this->_setJoin($type1, $type2, false, $select);
        }
        else
        {
            $this->_setJoin($type1, $type2);
        }

        return $this;
    }

    public function optional($type1, $type2 = null, $select = null)
    {
        if ($this->_isSelect($type2))
        {
            $select = $this->_parseSelect($type2);
            $this->_select($select);
            $this->_setJoin($type1, null, true, $select);
        }
        else if ($this->_isSelect($select))
        {
            $select = $this->_parseSelect($select);
            $this->_select($select);
            $this->_setJoin($type1, $type2, true, $select);
        }
        else
        {
            $this->_setJoin($type1, $type2, true);
        }

        return $this;
    }

    private function _setType($table)
    {
        // setup tables
        $this->_setAlias($table);
        $table = $this->_setJoinedTable($table);

        $this->_from = array('table' => $table, 'alias' => $this->_aliases[$table]);

        return $this;
    }

    private function _setJoin($type1, $type2 = null, $optional = false, $select = null)
    {
        // If only one type specified then $this->_from = $type2
        $type2 = $type2 ? $type2 : $this->_from['table'];

        // setup aliases
        $this->_setAlias($type1, $type2);

        // get just the table names
        $table1 = $this->_getTableName($type1);
        $table2 = $this->_getTableName($type2);

        // Figure out the join table name
        $join_table     = $this->_getLookupName($table1, $table2);
        $join_keyword   = $optional ? 'LEFT JOIN' : 'JOIN';

        // Properly order the tables
        $tables = explode('#', $join_table);
        $table1 = $tables[0];
        $table2 = $tables[1];

        // Force aliases from stored location - (checks for previously set aliases)
        $alias1 = $this->_aliases[$table1];
        $alias2 = $this->_aliases[$table2];

        // Check for alias of the join table
        $join_alias = $this->_join_aliases[$join_table];

        // Figure out the proper join sequence (based on tables already in $this->_tables)
        if (in_array($table1, $this->_tables))
        {
            $table_alias1   = $alias1 ? $alias1 : $table1;

            if ($join_alias)
            {
                $this->_joins[] =   " $join_keyword `" . $join_table . "` " . $join_alias .
                                    " ON " . $table_alias1 . ".pk = `" . $join_alias . "`.pk1 ";
            }
            else
            {
                $this->_joins[] =   " $join_keyword `" . $join_table . "` " .
                                    " ON " . $table_alias1 . ".pk = `" . $join_table . "`.pk1 ";
            }

            if ($alias2)
            {
                $join_table = $join_alias ? $join_alias : $join_table;
                $join_sql   = " $join_keyword " . $table2 . ' ' . $alias2 . " ON `" . $join_table . "`.pk2 = " . $alias2 . ".pk";

                if ($optional)
                {
                    $join_sql .= " AND " . $alias2 . '.context IN (' . $this->_context_pks . ') ';
                }
            }
            else
            {
                $join_table = $join_alias ? $join_alias : $join_table;
                $join_sql   = " $join_keyword " . $table2 . " ON `" . $join_table . "`.pk2 = " . $table2 . ".pk ";

                if ($optional)
                {
                    $join_sql .= " AND " . $table2 . '.context IN (' . $this->_context_pks . ') ';
                }
            }

            $this->_joins[] = $join_sql;
        }
        else
        {
            $table_alias2   = $alias2 ? $alias2 : $table2;

            if ($join_alias)
            {
                $this->_joins[] = " $join_keyword `" . $join_table . "` " . $join_alias .
                                  " ON " . $table_alias2 . ".pk = `" . $join_alias . "`.pk2 ";
            }
            else
            {
                $this->_joins[] = " $join_keyword `" . $join_table . "` " .
                                  " ON " . $table_alias2 . ".pk = `" . $join_table . "`.pk2 ";
            }


            if ($alias1)
            {
                $join_table = $join_alias ? $join_alias : $join_table;
                $join_sql   = " $join_keyword " . $table1 . ' ' . $alias1 . " ON `" . $join_table . "`.pk1 = " . $alias1 . ".pk ";

                if ($optional)
                {
                    $join_sql .= " AND " . $alias1 . '.context IN (' . $this->_context_pks . ') ';
                }
            }
            else
            {
                $join_table = $join_alias ? $join_alias : $join_table;
                $join_sql   = " $join_keyword " . $table1 . " ON `" . $join_table . "`.pk1 = " . $table1 . ".pk ";

                if ($optional)
                {
                    $join_sql .= " AND " . $table1 . '.context IN (' . $this->_context_pks . ') ';
                }
            }

            $this->_joins[] = $join_sql;
        }

        // Add tables to the stack
        $this->_setJoinedTable($table1, $optional);
        $this->_setJoinedTable($table2, $optional);

        return $this;
    }

    public function where($sql)
    {
        $this->_where = $sql;

        return $this;
    }

    public function order($sql)
    {
        $this->_order = $sql;

        return $this;
    }

    public function limit($start, $limit = null)
    {
        if ($limit)
        {
            $this->_limit = " $start, $limit ";
        }
        else
        {
            $this->_limit = " $start "; // Start is the limit
        }

        return $this;
    }

    public function query($asarray)
    {
        $sql = $this->getSql();

        if ($asarray)
        {
            $dbc        = new DatabaseConnection();
            $cluster    = $dbc->query($sql);
        }
        else
        {
            // Handle cluster creation
            $cluster = new Cluster($this->_from['table']);
            $cluster->populate($this->_from['table'], $sql);
        }

        return $cluster;
    }

    public function getSql()
    {
        // Build the query

        $pk_table = $this->_from['alias'] ? $this->_from['alias'] : $this->_from['table'];

        if (!empty($this->_select))
        {
            $select     = $pk_table . '.pk, ';
            $select     .= implode(', ', $this->_select);
        }
        else
        {
            $select = $pk_table . '.* ';
        }

        // Build where
        $where = '';

        foreach ($this->_required_tables as $table)
        {
            $table = $this->_aliases[$table] ? $this->_aliases[$table] : $table;

            $where .= ' AND ' . $table . '.context IN (' . $this->_context_pks . ') ';
        }

        // Append where
        if ($this->_where)
        {
            $where .= ' AND ' . $this->_where;
        }

        if ($this->_order)
        {
            $order = ' ORDER BY ' . $this->_order;
        }

        if ($this->_limit)
        {
            $limit = ' LIMIT ' . $this->_limit;
        }

        $sql =  ' SELECT ' . $select . ' ' .
                ' FROM ' . $this->_from['table'] . ' ' . $this->_from['alias'] . ' ' .
                    implode(' ', $this->_joins) . ' ' .
                ' WHERE 1 ' . $where . ' ' .
                $order . ' ' .
                $limit;

        return $sql;
    }

    private function _getTableName($string)
    {
        // Get just the table names
        $parts = $this->_parseTableName($string);

        return $parts['table'];
    }

    private function _getLookupName($type1, $type2)
    {
        // Order the name of the table
        $arr    = array();
        $arr[]  = $type1;
        $arr[]  = $type2;

        sort($arr);

        $table_prefix   = $arr[0];
        $table_suffix   = $arr[1];

        return "$table_prefix#$table_suffix";
    }

    private function _setJoinedTable($table, $optional = false)
    {
        $parts = $this->_parseTableName($table, $from_clause);

        // Store table
        if (!in_array($parts['table'], $this->_tables))
        {
            $this->_tables[] = $parts['table'];
        }

        if ($optional)
        {
            if (!in_array($parts['table'], $this->_optional_tables))
            {
                $this->_optional_tables[] = $parts['table'];
            }
        }
        else
        {
            if (!in_array($parts['table'], $this->_required_tables))
            {
                $this->_required_tables[] = $parts['table'];
            }
        }

        return $parts['table'];
    }

    private function _setAlias($type1, $type2 = null)
    {
        $parts  = $this->_parseTableName($type1);
        $table1 = $parts['table'];
        $alias1 = $parts['alias'];

        $join_alias1 = $parts['join_alias'];

        // Store the alias
        if ($alias1)
        {
            $this->_aliases[$table1] = $alias1;
        }

        if ($type2)
        {
            $parts  = $this->_parseTableName($type2);
            $table2 = $parts['table'];
            $alias2 = $parts['alias'];

            $join_alias2 = $parts['join_alias'];

            // Store the alias
            if ($alias2)
            {
                $this->_aliases[$table2] = $alias2;
            }
        }

        if ($join_alias1 || $join_alias2)
        {
            $join_table = $this->_getLookupName($table1, $table2);
            $join_alias = $join_alias2 ? $join_alias2 : $join_alias1;

            $this->_join_aliases[$join_table] = $join_alias;
        }

        return;
    }

    /**
     * Requires one of the following formats
     * table
     * table alias
     * table alias join_alias
     * @param string $table
     * @return mixed
     */
    private function _parseTableName($table)
    {
        $sql    = explode(' ', $table);
        $table  = array();

        $table['table']         = $sql[0];
        $table['alias']         = $sql[1];
        $table['join_alias']    = $sql[2];

        return $table;
    }

}
