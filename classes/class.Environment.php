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
 * This class determines the current environment name, and provides an interface to retrieve environment properties
 */

require_once('class.ExceptionHandler.php');

class Environment
{
    private static $environment = false;
    
    public static function GetProperty($key)
    {
        return Config::GetValue($key);
    }
    
    /**
     * Sets the environment explicitly
     * 
     * @param string $name
     * @return boolean
     */
    public static function SetEnvironment($name)
    {
        if (strlen($name) > 0)
        {
            self::$environment = $name;
            return true;
        } 
        
        return false;
    }
    
    /**
     * Returns the name of your current environment
     * 
     * The appropriate method to determine the environment name is decided by the developers.
     * In our case it's based on the URL prefix.
     * 
     * @return string               The name of the current environment (such as 'dev', 'proof', 'live')
     */
    public static function GetName()
    {
        // First check for explicit environment overrides
        if (self::$environment)
        {
            return self::$environment;
        }
        else
        {
            $sub_domain = explode('.', $_SERVER['HTTP_HOST']);
            $sub_domain = $sub_domain[0];
            
            switch ($sub_domain)
            {
                // Explicit environments
                case 'dev':
                case 'proof':
                    return $sub_domain;       
                    break;
                    
                default:
                    return 'live';       
                    break;
            }
        }
    }
    
    
    /**
     * Tells you whether you are in a production environment
     * 
     * @return boolean              True if this is a production (live) environment
     */
    public static function IsLive()
    {
        $name = self::GetName(); 
        
        switch ($name)
        {
            // Non production environments
            case 'dev':
            case 'proof':
                return false;
                break;

            // Production environments
            case 'live':
            default:
                return true;
                break;
        }
    }
}
