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
 * This class is meant to provide various means of handling errors
 */

class ExceptionHandler
{
    public static function SendToFile($e)
    {
        // Get the log file from config
        $log = Config::GetValue('error_log');
        
        // In case the exception is based on environment/config errors set up default log
        if (!$log)
        {
            $log = '/var/www/enleap/apache/default_error.log';
        }
        
        $error =    $e->getFile().': Line '.$e->getLine()."\n".
                    $e->getMessage()."\n".
                    $e->getTraceAsString()."\n\n";
        
        $handle = fopen($log, 'ab');
        fwrite($handle, $error);
        fclose($handle);

        return null;
    }

    public static function SendToScreen($e)
    {
        $error =    $e->getFile().': Line '.$e->getLine()."\n<br/>".
                    $e->getMessage()."\n<br/>".
                    $e->getTraceAsString()."\n\n<br/></br/>";
        
        echo "<pre style='background: #ff0000; color: #fff; font-weight: bold; padding: 10px;'>$error</pre>";
        return null;
    }
    
    public static function SendToJson($e)
    {
        $error['error'] =   $e->getFile().': Line '.$e->getLine()."\n<br/>".
                            $e->getMessage()."\n<br/>".
                            $e->getTraceAsString()."\n\n<br/></br/>";
        
        return json_encode($error);
    }
    
    public static function SendToEmail($e, $email = null)
    {
    	// Set up your own Email handler class or refer this to an existing one that you have implemented.
    }
}


?>
