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

class MeshTools
{
    private static $_ContextPks = array();

    private static $_Context;

    private static $_SavedContextPks = array();

    private static $_SavedContext;

    private static $_DefaultContextPk;

    private static $_DefaultContext;

    private static $_HistoryTrigger;

    private static $_HistoryTypes = array();

    private static $_HistoryActions = array();

    public static $GlobalDebug = false;

    public static $DebugCounter = 0;

    public static function StartDebugging($rest_counter = false)
    {
        self::$GlobalDebug = true;
    }

    public static function StopDebugging()
    {
        self::$GlobablDebug = false;
    }

    public static function GetDebugCount()
    {
        return self::$DebugCounter;
    }

    public static function RestoreContext()
    {
        self::$_ContextPks   = self::$_SavedContextPks;
        self::$_Context      = self::$_SavedContext;
    }

    public static function SetContext($name, $save = false)
    {
        // Save previous context
        if ($save)
        {
            self::$_SavedContextPks  = self::$_ContextPks;
            self::$_SavedContext     = self::$_Context;
        }

        // Check for unsetting of context
        if ('*' == $name)
        {
            self::$_ContextPks   = array();
            self::$_Context      = null;
        }
        else
        {
            self::$_ContextPks = self::GetContextPks($name);
            self::$_Context    = $name;

            if (empty(self::$_ContextPks))
            {
                throw new Exception("Unknown context '$name'");
            }
        }

        return;
    }

    public static function SetDefaultContext($name)
    {
        if ('*' == $name)
        {
            throw new Exception("Default context cannot be '*'");
        }
        else
        {
            $context_pk = self::GetContextPks($name);

            self::$_DefaultContextPk    = $context_pk[0];
            self::$_DefaultContext      = $name;
        }
    }

    public static function GetContextPks($name = null)
    {
        if (0 == func_num_args())
        {
           return self::$_ContextPks;
        }

        $names = explode(',', $name);

        foreach ($names as &$n)
        {
            $n = "'" . mysql_escape_string($n) . "'";
        }

        $names = implode(',', $names);

        $pks = array();

        if (! empty($names))        // avoid SQL error on empty list
        {
            $sql      = "SELECT * FROM node_contexts WHERE name IN ($names)";
            $conn     = new DatabaseConnection();
            $rows     = $conn->query($sql);

            foreach ($rows as $row)
            {
                $pks[] = $row['pk'];
            }
        }

        return $pks;
    }

    public static function GetDefaultContextPk()
    {
       return self::$_DefaultContextPk;
    }

    public static function GetDefaultContext()
    {
       return self::$_DefaultContext;
    }

    public static function GetContext($pks = null)
    {
        if (0 == func_num_args())
        {
            return self::$_Context;
        }

        if (is_array($pks))
        {
            $pks = implode(',', $pks);
        }

        $sql    = "SELECT * FROM node_contexts WHERE pk IN ($pks)";
        $dbc    = new DatabaseConnection();
        $rows   = $dbc->query($sql);

        foreach ($rows as $row)
        {
            $names[] = $row['name'];
        }

        return implode(',', $names);
    }

    public static function GetSavedContext()
    {
        return self::$_SavedContext;
    }

    public static function GetSavedContextPks()
    {
        return self::$_SavedContextPks;
    }

    public static function GetContextFromCluster($cluster)
    {
        $context = array();

        foreach ($cluster as $node)
        {
            $context[] = self::GetContext($node->context);
        }

        return $context;
    }

    public static function AddContext($name)
    {
        $name = mysql_escape_string($name);
        $sql  = "INSERT INTO node_contexts (name) VALUES ('$name')";

        $dbc  = new DatabaseConnection();
        $dbc->query($sql);

        return;
    }

    public static function DeleteContext($name)
    {
        $name = mysql_escape_string($name);
        $sql  = "DELETE FROM node_contexts WHERE name = '$name'";

        $dbc  = new DatabaseConnection();
        $dbc->query($sql);

        return;
    }

    public static function CommitNodeType($type, array $properties = array())
    {
        $columns            = implode(', ', $properties);
        $constraint         = $type.'_constraint';
        $context_constraint = $type.'_context_constraint';

        $dbc = new DatabaseConnection();

        // Make the table
        $sql = "CREATE TABLE $type (
                    pk INT(11) NOT NULL AUTO_INCREMENT,
                    context INT(11) NULL DEFAULT NULL,
                    $columns,
                    PRIMARY KEY  (pk),
                    CONSTRAINT $context_constraint FOREIGN KEY (context) REFERENCES node_contexts (pk)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

        $dbc->query($sql);


        $pk = mysql_insert_id();

        return $pk;
    }


    public static function DeleteNodeType($type)
    {
        $dbc = new DatabaseConnection();

        // Wipe it out
        $sql = "DROP TABLE $type";
        $dbc->query($sql);

        return $dbc->query($sql);
    }

    public static function AddUniqueConstraint($type, $attributes)
    {
        if (is_string($attributes))
        {
            $columns = $attributes;
        }
        elseif (is_array($attributes))
        {
            $columns = implode(', ', $attributes);
        }
        else
        {
            throw new Exception('Unique attributes must be passed as a string or an array');
        }

        $unique_name    = uniqid();
        $dbc            = new DatabaseConnection();
        $sql            = "ALTER TABLE `$type` ADD UNIQUE `$unique_name` ($columns);";

        return $dbc->query($sql);
    }

    /**
     * This method accepts two node types and creates the needed lookup tables
     * @param $node_type1
     * @param $node_type2
     * @param $link_type can be one of the following: 'one', 'one-many', 'many'
     * @return unknown_type
     */
    public static function CommitLinkType($node_type1, $node_type2, array $link_attributes = array(), $cardinality = 'many')
    {
        $dbc = new DatabaseConnection();

        // @todo: add support for cardinality 'one', and 'one-many'

        // Order the name of the new table
        $arr[] = $node_type1;
        $arr[] = $node_type2;
        sort($arr);

        $table_prefix   = $arr[0];
        $table_suffix   = $arr[1];
        $table_name     = "$table_prefix#$table_suffix";

        $constraint1    = $table_prefix.'_'.$table_suffix.'_constraint_1';
        $constraint2    = $table_prefix.'_'.$table_suffix.'_constraint_2';

        $columns = '';
        if (count($link_attributes))
        {
            $columns        = implode(', ', $link_attributes) . ',';
        }

        // Update if the table already exists
        if (self::_GetLookupTable($table_name))
        {
            throw new Exception('Altering link types is not yet supported via the api, use another tool to add new attributes');
        }
        else
        {

            // Make the table
            $sql = "CREATE TABLE  `$table_name` (
              pk1 INT(11) NOT NULL,
              pk2 INT(11) NOT NULL,
              label VARCHAR(15) NOT NULL,
              direction ENUM('BIDI','LTR','RTL') NOT NULL DEFAULT 'BIDI',
              $columns
              PRIMARY KEY  (pk1, pk2),
              KEY $constraint1 (pk1),
              KEY $constraint2 (pk2),
              CONSTRAINT $constraint1 FOREIGN KEY (pk1) REFERENCES $table_prefix (pk),
              CONSTRAINT $constraint2 FOREIGN KEY (pk2) REFERENCES $table_suffix (pk)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;";
        }

        $dbc->query($sql);

        return $table_name;
    }

    /**
     * This wipes out the link table entirely, all data is lost
     * @param $node_type1
     * @param $node_type2
     * @return unknown_type
     */
    public static function DeleteLinkType($node_type1, $node_type2)
    {
        $dbc = new DatabaseConnection();

        // Order the name of the table
        $arr[] = $node_type1;
        $arr[] = $node_type2;
        sort($arr);

        $table_prefix   = $arr[0];
        $table_suffix   = $arr[1];
        $table_name     = "$table_prefix#$table_suffix";

        // Drop the table
        $sql = "DROP TABLE `$table_name` ";

        $dbc->query($sql);
    }

    private static function _EscapeProperties(&$property)
    {
        $property = mysql_escape_string($property);
    }

    public static function DeleteNode(&$node)
    {
        // TODO: Move this into the Node class

        if (!$node->pk)
        {
            $node = null;
            return;
        }

        $dbc = new DatabaseConnection();

        // Get the node table
        $type       = $node->getType();
        $lookups    = self::_GetLookupTables($type);

        // wipe out the links
//        $names = array();
        foreach ($lookups as $lookup)
        {
            $name   = $lookup['table_name'];
            $sql    = "DELETE FROM `$name` WHERE $node->pk IN (pk1, pk2)";
            $dbc->query($sql);

//            $names[] = $name;
        }
//        $lookups = '`'.implode('`.*,`', $names).'`.*';


        // Not sure why this doesn't work as it works fine if run directly in mysql and other clients
//        $sql = "
//            START TRANSACTION;
//                DELETE FROM $type WHERE pk = $node->pk;
//                DELETE FROM $lookups WHERE $node->pk IN (pk1, pk2);
//            COMMIT;
//        ";
//        $dbc->query($sql);

        // Splitting the transaction into three queries as the transaction is failing for some reason
        $sql = "DELETE FROM $type WHERE pk = $node->pk";
        $dbc->query($sql);

        // Remove the node contents from memory
        $node = null;

        return;
    }

    public static function DeleteCluster(&$cluster)
    {
        // TODO: Move this into the cluster class

        if (count($cluster) > 0)
        {
            $dbc = new DatabaseConnection();

            // Get the tables
            $type       = $cluster->getType();
            $lookups    = self::_GetLookupTables($type);

            // Get all the node pks
            foreach ($cluster as $node)
            {
                $pks[] = $node->pk;
            }
            $pks = implode(',', $pks);

            // wipe out the links
            foreach ($lookups as $lookup)
            {
                $name   = $lookup['table_name'];
                $sql    = "DELETE FROM `$name` WHERE (pk1 IN ($pks) OR pk2 IN ($pks))";
                $dbc->query($sql);
            }

            $sql = "DELETE FROM $type WHERE pk IN ($pks)";
            $dbc->query($sql);

            // Remove the cluster contents from memory
            $cluster = array();
        }
        return;
    }

    /**
     * Returns an array of lookup tables for the given type
     * @param $type
     * @return unknown_type
     */
    private static function _GetLookupTables($type)
    {
        // Get the lookup tables
        $dbc        = new DatabaseConnection();
        $schema     = $dbc->getDatabase();

        $sql        = "SELECT table_name FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = '$schema'
                        AND (table_name LIKE '$type#%' OR table_name LIKE '%#$type')";
        $lookups    = $dbc->query($sql);

        return $lookups;
    }

    private static function _GetLookupTable($table)
    {
        // Get the lookup tables
        $dbc        = new DatabaseConnection();
        $schema     = $dbc->getDatabase();
        $sql        = "SELECT table_name FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = '$schema' AND (table_name = '$table')";
        $lookup     = $dbc->query($sql);

        if (count($lookup))
        {
            return true;
        }

        return false;
    }

    public static function CreateMesh($name)
    {
        // Must use an administrative environment to create databases (GLOBAL CREATE PRIVILEGES)
        // Make sure the right environment is set
        $dbc = new DatabaseConnection();

        // Create the database
        $sql = "CREATE DATABASE IF NOT EXISTS `$name`;";
        $dbc->query($sql);
        $dbc->SelectDatabase($name);

        // Make the context table
        $sql = "CREATE TABLE IF NOT EXISTS `node_contexts` (
                    `pk` int(11) NOT NULL auto_increment,
                    `name` varchar(31) NOT NULL,
                    PRIMARY KEY  (`pk`),
                    UNIQUE KEY `name` (`name`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='latin1_swedish_ci';";
        $dbc->query($sql);

        // Add the history table
        $sql = "CREATE TABLE IF NOT EXISTS `node_history` (
                  `pk1` int(11) NOT NULL,
                  `type1` varchar(20) NOT NULL,
                  `pk2` int(11) DEFAULT NULL,
                  `type2` varchar(20) DEFAULT NULL,
                  `action` varchar(20) NOT NULL,
                  `date` datetime NOT NULL,
                  `trigger_pk` int(11) DEFAULT NULL,
                  `trigger_type` varchar(20) DEFAULT NULL,
                  `trigger_process` varchar(50) DEFAULT NULL,
                  KEY `pk1` (`pk1`),
                  KEY `pk2` (`pk2`),
                  KEY `trigger_node` (`trigger_pk`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
        $dbc->query($sql);

    }

    /**
     * returns all the nodes in cluster that are not linked to type2
     * @param $cluster1
     * @param $cluster2
     * @return unknown_type
     */
    public static function NotLinked($cluster, $type2, $pks_only = false, $select = false)
    {
        // Return the cluster if its empty
        if (count($cluster) < 1)
        {
            return $cluster;
        }

        // Get the cluster type
        $type1 = $cluster->getType();

        // Get the nodes in the cluster
        $arr = array();
        foreach ($cluster as $node)
        {
            $arr[] = $node->pk;
        }

        $pks = implode(',', $arr);

        // Figure out the table name
        $tmp[] = $type1;
        $tmp[] = $type2;
        sort($tmp);

        $join_prefix    = $tmp[0];
        $join_suffix    = $tmp[1];
        $join_table     = "$join_prefix#$join_suffix";

        // Figure out which side to join on
        if ($type1 == $join_prefix)
        {
            $join_pk = 'pk1';
        }
        else
        {
            $join_pk = 'pk2';
        }

        // Check to see which pks are not linked to type2
        $sql = "SELECT `$type1`.pk FROM `$type1`
                LEFT JOIN `$join_table` ON `$type1`.pk = `$join_table`.$join_pk
                WHERE `$type1`.pk NOT IN (SELECT $join_pk FROM `$join_table`)
                AND `$type1`.pk IN ($pks)
        ";

        $dbc = new DatabaseConnection();
        $pks = $dbc->query($sql);

        // Now create a new cluster with the non linked pks
        $arr = array();
        foreach ($pks as $pk)
        {
            $arr[] = $pk['pk'];
        }

        $pks = implode(',', $arr);

        if ($pks_only)
        {
            return $pks;
        }
        else
        {
            if (strlen($pks) > 0)
            {
                if (!$select)
                {
                    $select = 'null';
                }
                return new Cluster($type1, "in:pk:$pks", $select);
            }
            else
            {
                // Force an empty cluster
                return new Cluster($type1, 'eq:pk:0', $select);
            }
        }
    }

    public static function GetNodeTypes($mesh = null)
    {
        if (isset($mesh))
        {
            $mesh .= '.';
        }

        $sql    = "SELECT TABLE_NAME
                   FROM {$mesh}INFORMATION_SCHEMA.COLUMNS
                   WHERE COLUMN_NAME = 'pk' AND TABLE_NAME NOT LIKE '\_%'
                   ORDER BY TABLE_NAME";
        $dbc    = new DatabaseConnection();
        $types  = $dbc->query($sql);

        foreach ($types as $type)
        {
            $t[] = $type['TABLE_NAME'];
        }

        return $t;
    }

    public static function IsNodeType($type)
    {
        return (in_array($type, self::GetNodeTypes()));
    }
}
