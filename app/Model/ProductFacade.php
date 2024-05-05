<?php
namespace App\Model;

use Nette;

final class ProductFacade
{
	public function __construct(
		private Nette\Database\Explorer $database,
	) {
		$database->getConnection()->getPdo()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		register_shutdown_function([$this, 'processTerminatorHandler']);
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
