<?
/*
 * This script sets up a new mesh with some sample node types.
 * 
 * NOTE: Administrative database privileges are required. Edit setup.cfg to 
 * use proper credentials.
 */


// Autoload classes (make sure class.*.php are located in the include_path)

set_include_path('../classes:../includes');

include_once 'interface.DatabaseAdapterInterface.php';      // odd name, pull it in immediately

function __autoload($class)
{
    require "class.$class.php";
}


// Explicitly set the environment (overrides URL-based environment naming).

Environment::SetEnvironment('setup');


// Start by creating the base mesh

MeshTools::CreateMesh('middle_earth');


// Now create the node types for this mesh

// Hobbits
$attributes = array(
    'first_name VARCHAR(20)',
    'last_name VARCHAR(20)',
    'age INT',
);

MeshTools::CommitNodeType('hobbits', $attributes);

// Locations
$attributes = array(
    'name VARCHAR(20)',
    'guarded TINYINT(1) DEFAULT 1',
);

MeshTools::CommitNodeType('locations', $attributes);

// Treasures
$attributes = array(
    'name VARCHAR(20)',
);

MeshTools::CommitNodeType('treasures', $attributes);


// Next, define the link types (ie., how each node types links to any other
// node type)

// Hobbits can link to Locations
MeshTools::CommitLinkType('hobbits', 'locations');

// Hobbits can link to Treasures
MeshTools::CommitLinkType('hobbits', 'treasures');

// Hobbits can link to Hobbits (with named relationships)
// NOTE: We will be using the built-in 'label' attribute to identify friendship (ie. 'eq:label:friendship')
MeshTools::CommitLinkType('hobbits', 'hobbits');

// Locations can link to Locations (with direction)
// NOTE: We will be using the built-in 'direction' attribute to identify direction (ie. 'dir:forward')
MeshTools::CommitLinkType('locations', 'locations');
