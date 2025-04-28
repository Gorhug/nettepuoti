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
namespace App\Model;

use Nette;

final class ProductFacade
{
	public function __construct(
		private Nette\Database\Explorer $database,
	) {
		$database->getConnection()->getPdo()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		// register_shutdown_function([$this, 'processTerminatorHandler']);
	}

	public function processTerminatorHandler(): void
    {
        // this logic will be called by Terminator.
		$this->database->getConnection()->getPdo()->exec('PRAGMA optimize');
    }

	public function getPublicProducts()
	{
		return $this->database
			->table('products')
			->where('created_at < ', (new \DateTimeImmutable())->format(DATE_ATOM))
			->order('created_at DESC');
	}
}
