<?php
/*
Copyright Ilkka Forsblom.

This file is part of Nettepuoti.

Nettepuoti is free software: you can redistribute it and/or modify 
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the 
License, or (at your option) any later version.

Nettepuoti is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License 
along with Nettepuoti. If not, see <https://www.gnu.org/licenses/>. 
*/
namespace App;

class Settings
{
	public function __construct(
		// since PHP 8.1 it is possible to specify readonly
		// public bool $debugMode,
		// public string $appDir,
		// and so on
        public string $entsoeToken,
		public string $adminEmail,
		public string $adminName,
		public string $botEmail,
		public string $wwwDir,
		public string $uploadDir
	) {}
}
