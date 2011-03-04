<?
/*
 * This script shows examples of Node Mesh usage. Edit main.cfg as needed.
 */


// Autoload classes (make sure class.*.php are located in the include_path)

set_include_path('../classes:../includes');

include_once 'interface.DatabaseAdapterInterface.php';      // odd name, pull it in immediately

function __autoload($class)
{
    require "class.$class.php";
}


// Explicitly set the environment (overrides URL-based environment naming).

Environment::SetEnvironment('main');


// Create a new node, and commit it to the database
$bilbo = new Node('hobbits');

$attributes = array(
    'first_name'    =>  'Bilbo',
    'last_name'     =>  'Baggins',
    'age'           =>  111,
);

$bilbo->commit($attributes);

// Create another node
$the_ring = new Node('treasures');
$the_ring->commit(array('name' => 'The Ring'));


// Link some nodes
// Only nodes that have been committed can be linked
$the_ring->link($bilbo);


// Add some locations
$shire = new Node('locations');
$shire->commit(array('name' => 'The Shire'));

$bilbo->link($shire);

$bree = new Node('locations');
$bree->commit(array('name' => 'Bree'));

$mirkwood = new Node('locations');
$mirkwood->commit(array('name' => 'Mirkwood'));


// Link the locations together, with directions for the preferred travel route
$shire->link($bree, array('direction' => 'forward'));
$bree->link($mirkwood, array('direction' => 'forward', 'label' => 'DANGEROUS'));


// Get the next location from the $shire
$next_stop = $shire->locations('dir:forward');

// Get all DANGEROUS paths joining $bree
$hazards = $bree->locations('rel:DANGEROUS');


// Destroy a node

MeshTools::DeleteNode($the_ring);
