<?
/*
 * This script sets up a new mesh with some sample node types.
 * 
 * NOTE: Administrative database privileges are required. Edit setup.cfg to 
 * use proper credentials.
 */


// Simple include_path and autoload configuration

require_once '../includes/config.php';


// Explicitly set the environment (use example.cfg, not default URL-based name)

Environment::SetEnvironment('setup');


// Create a new mesh

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


// Define the link types (how each node type links to other node types)

// Hobbits can link to Locations (order is not important)
MeshTools::CommitLinkType('hobbits', 'locations');

// Hobbits can link to Treasures
MeshTools::CommitLinkType('hobbits', 'treasures');

// Links automatically have 'label' and 'direction' attributes

// Hobbits can link to Hobbits (we'll use link labels for relationships)
MeshTools::CommitLinkType('hobbits', 'hobbits');

// Locations can link to Locations (we'll use link directions for directions)
MeshTools::CommitLinkType('locations', 'locations');


echo "New node mesh has been created!\n";
