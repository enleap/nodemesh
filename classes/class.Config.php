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

/*
 * @todo Decide whether this class should be purely static or not
 * @todo Abstract out the different ways to pull configuration
 */
class Config
{
    /**
     * Local cache of configuration data
     * 
     * @var array
     */
    private static $_Config;        // initialize to null
    
    
    /**
     * Gets a single value corresponding to a particular configuration key
     * 
     * @param string $key           The configuration key
     * @param mixed $default        (Optional) A default value to return if there is no configuration for this key
     * 
     * @return mixed                A configuration value (normally a scalar), or null if not found
     */
    public static function GetValue($key, $default = null)
    {
        $config = self::GetConfig();

        return array_key_exists($key, $config) ? $config[$key] : $default;
    }
    
    
    /**
     * Gets an array of values corresponding to a particular configuration key
     * 
     * @param string $key           The configuration key
     * @param array $default        (Optional) An array of default values if there is no configuration for this key
     * 
     * @return array                An array of configuration values (empty if not found)
     */
    public static function GetValues($key, array $default = array())
    {
        $config = self::GetConfig();

        return array_key_exists($key, $config[$class]) ? (array) $config[$key] : $default;
    }
    
    
    /**
     * Gets all configuration data for this class
     * 
     * @return array        An array of key-value pairs (some values may also be arrays)
     */
    public static function GetConfig()
    {
        // Cache the config if needed
        self::_LoadConfig();

        $class = self::GetClass();

        return (array) @self::$_Config[$class];
    }
    
    
    /**
     * Gets the "class" (section) that contains the configuration for the current code
     * 
     * @return string           The name of the appropriate class
     * 
     * @throws Exception        If the appropriate class cannot be determined; this should never happen
     * 
     * The class returned is based on the object class of $this, the current class, the current function, or 
     * the current file being executed. Most of the time, It Just Works, and acts the way you would expect.
     */
    public static function GetClass()
    {
        $db           = debug_backtrace();
        $defaultClass = null;

        foreach ($db as $trace)
        {
            if (isset($trace['object']))
            {
                return get_class($trace['object']);
            }
            elseif (isset($trace['class']) && __CLASS__ != $trace['class'])
            {
                return $trace['class'];
            }
            elseif (! isset($defaultClass))
            {
                if (isset($trace['function']) && __CLASS__ != $trace['class'])
                {
                    $defaultClass = $trace['function'];
                }
                elseif (__FILE__ != $trace['file'])
                {
                    $defaultClass = basename($trace['file']);
                }
            }
        }

        if (isset($defaultClass))
        {
            return $defaultClass;
        }
        else
        {
            throw new Exception('Unable to determine appropriate Config class');
        }
    }
    
    
    /**
     * Load and cache the configuration data (currently always from a file)
     * 
     * @return void
     * 
     * @throws Exception    If config cannot be loaded for any reason
     */
    private static function _LoadConfig()
    {
        if (! isset(self::$_Config))
        {
            $file          = Environment::GetName() . '.cfg';
            self::$_Config = self::_GetConfigFromFile($file);
        }
        
        return;
    }
    
    
    /**
     * This reads in an INI config file.
     * 
     * @param $file         Path to the config file to parse
     * 
     * @return array        A multidimensional array of configuration data
     * 
     * @throws Exception    If file cannot be parsed for any reason
     */
    private static function _GetConfigFromFile($file)
    {
        $config = parse_ini_file($file, true);
        
        if (false === $config)
        {
            throw new Exception('Config file is unreadable or corrupted');
        }
        
        return $config;
    }
}
