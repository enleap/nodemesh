<?
/*
 * Simple include_path and autoload configuration for Node Mesh usage.
 */


// Make sure class/interface files can be found

set_include_path(get_include_path() . ':../classes:.');


// Autoload classes and interfaces

spl_autoload_register(function ($class)
{
    require "class.$class.php";
});
spl_autoload_register(function ($interface)
{
    require "interface.$interface.php";
});


// Explicitly set the environment if you want to use a custom .cfg file:
// Environment::SetEnvironment('example');

