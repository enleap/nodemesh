<?
/*
 * This script shows examples of Node Mesh usage. Edit example.cfg as needed.
 */


// Simple include_path and autoload configuration

require_once '../includes/config.php';


// Explicitly set the environment (use example.cfg, not default URL-based name)

Environment::SetEnvironment('example');


// Create a new node

$bilbo = new Node('hobbits');

$bilbo->first_name = 'Bilbo';
$bilbo->last_name  = 'Baggins';
$bilbo->age        = 111;

$bilbo->commit();       // save $bilbo to the database


// Create another node

$the_ring = new Node('treasures');
$the_ring->commit(array('name' => 'The Ring')); // alternate attribute syntax


// Link some nodes
// Only nodes that have been committed can be linked

$the_ring->link($bilbo);        // $bilbo->link($the_ring) would also work


// Add some locations

$shire       = new Node('locations');
$shire->name = 'The Shire';
$shire->commit();

$bilbo->link($shire);

$bree       = new Node('locations');
$bree->name = 'Bree';
$bree->commit();

$mirkwood       = new Node('locations');
$mirkwood->name = 'Mirkwood';
$mirkwood->commit();


// Link the locations together, with directions for the preferred travel route

$shire->link($bree, array('direction' => 'forward'));
$bree->link($mirkwood, array('direction' => 'forward', 'label' => 'DANGEROUS'));


// Get the next location from the $shire
$next_stop = $shire->locations('dir:forward');

// Get all DANGEROUS paths joining $bree
$hazards = $bree->locations('rel:DANGEROUS');


// Destroy a node

MeshTools::DeleteNode($the_ring);
