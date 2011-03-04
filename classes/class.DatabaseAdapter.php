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

class DatabaseAdapter
{
    public static function Create($adapter)
    {
        if (! class_exists($adapter))
        {
            throw new Exception("Missing DatabaseAdapterInterface implementation class: $adapter");
        }

        $ref = new ReflectionClass($adapter);

        if ($ref->implementsInterface('DatabaseAdapterInterface'))
        {
            return new $adapter();
        }
        else
        {
            throw new Exception("Invalid DatabaseAdapterInterface implementation class: $adapter");
        }
    }
}
