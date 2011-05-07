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

class History
{
    public static function Retrieve(array $options = array())
    {
        $users      = $options['users']     ? $options['users']     : null;
        $actions    = $options['actions']   ? $options['actions']   : null;
        $nodes      = $options['nodes']     ? $options['nodes']     : null;
        $types      = $options['types']     ? $options['types']     : null;
        $start      = $options['start']     ? $options['start']     : 0;
        $top        = $options['top']       ? $options['top']       : 100;

        $start_date = $options['start_date']    ? $options['start_date']    : null;
        $end_date   = $options['end_date']      ? $options['end_date']      : null;

        $nodes_sql      = self::_BuildSQL('nodes', $nodes);
        $types_sql      = self::_BuildSQL('types', $types);
        $actions_sql    = self::_BuildSQL('actions', $actions);
        $users_sql      = self::_BuildSQL('users', $users);
        $date_sql       = self::_BuildSQL('date', array($start_date, $end_date));

        // Limit search to current context until new history system in place
        $context = MeshTools::GetContextPks();
        $context = implode(',', $context);
        if($context)
        {
            $context_sql = "AND user_context IN ($context) AND context1 IN ($context)";
        }
        else
        {
            $context_sql = '';
        }

        // Grab all the records based on filters
        $sql = "SELECT * FROM node_history WHERE 1
                    $nodes_sql
                    $types_sql
                    $actions_sql
                    $users_sql
                    $date_sql
                    $context_sql

                    LIMIT $start, $top
                ";

        $dbc    = new DatabaseConnection();
        $rows   = $dbc->query($sql);

        return new HistoryCollection($rows);
    }

    private static function _BuildSQL($key, $value)
    {
        switch ($key)
        {
            case 'nodes':
                $sql = $value ? self::_BuildNodesSQL($value) : '';
                break;

            case 'types':
                if ($value)
                {
                    $value  = self::_CleanList($value);
                    $sql    = $value ? "AND type1 IN($value)" : '';
                }
                break;

            case 'actions':
                if ($value)
                {
                    $value  = self::_CleanList($value);
                    $sql    = $value ? "AND (action IN($value))" : '';
                }
                break;

            case 'users':
                $sql = $value ? self::_BuildUsersSQL($value) : '';
                break;

            case 'date':
                $start_date = $value[0];
                $end_date   = $value[1];

                if ($start_date && $end_date)
                {
                    $sql = "AND date >= '$start_date' AND date <= '$end_date'";
                }
                break;

            default:
                break;
        }

        return $sql;
    }

    private static function _BuildNodesSQL($nodes)
    {
        if ($nodes instanceOf Cluster)
        {
            $pks = array();

            foreach ($nodes as $node)
            {
                $pks[] = $node->pk;
            }

            $pks = implode(',', $pks);

            $sql = "AND pk1 IN($pks)";
        }
        else if ($nodes instanceOf Node)
        {
            $pk = $node->pk;

            $sql = "AND pk1 = $pk";
        }

        return $sql;
    }

    private static function _CleanList($list)
    {
        $list = explode(',', $list);

        // Escape each item
        array_walk($list, array('History', '_EscapeString'));

        // Rebuild the string in quotes
        $list = "'" . implode('\',\'', $list) . "'";

        return $list;
    }

    private static function _BuildUsersSQL($users)
    {
        if ($users instanceOf Cluster)
        {
            $pks = array();

            foreach ($users as $node)
            {
                $pks[] = $node->pk;
            }

            $pks = implode(',', $pks);

            $sql = "AND (user_pk IN($pks))";
        }
        else if ($users instanceOf Node)
        {
            $pk = $users->pk;

            $sql = "AND (user_pk = $pk)";
        }
        else
        {
            $sql = "";
        }

        return $sql;
    }

    public static function Log(Node $user, $action, Node $node1, $summary)
    {
        $type1      = $node1->getType();
        $pk1        = $node1->pk;
        $context1   = $node1->context;

        // Escape the summary
        self::_EscapeString($summary);

        $date = date('Y-m-d G:i:s');

        $sql = "INSERT INTO node_history (user_pk, user_context, pk1, type1, context1, action, date, summary)
                VALUES ($user->pk, $user->context, $pk1, '$type1', $context1, '$action', '$date', '$summary')
                ";

        $dbc    = new DatabaseConnection();
        $rows   = $dbc->query($sql);

        return true;
    }

    private function _EscapeString(&$value)
    {
        $value = mysql_escape_string($value);
    }

}


?>
