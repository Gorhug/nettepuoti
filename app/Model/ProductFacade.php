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
			->where('created_at < ', new \DateTime)
			->order('created_at DESC');
	}
}
