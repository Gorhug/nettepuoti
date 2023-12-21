<?php
namespace App\Model;

use Nette;

final class ProductFacade
{
	public function __construct(
		private Nette\Database\Explorer $database,
	) {
	}

	public function getPublicProducts()
	{
		return $this->database
			->table('products')
			->where('created_at < ', (new \DateTimeImmutable())->format(DATE_ATOM))
			->order('created_at DESC');
	}
}
