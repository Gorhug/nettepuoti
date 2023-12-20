<?php
namespace App\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;

final class ProductPresenter extends BasePresenter
{
	public function __construct(
		private Nette\Database\Explorer $database,
	) {
	}

	public function renderShow(int $id): void
	{
		$product = $this->database
			->table('products')
			->get($id);

		if (!$product) {
			$this->error('Product not found');
		}

		$this->template->product = $product;
	}
// 	public function renderShow(string $name): void 
// 	{
// 		$product = $this->database
// 			->table('products')
// 			->where('name', $name)
// 			->fetch();

// 		if (!$product) {
// 			$this->error('Product not found');
// 		}

// 		$this->template->product = $product;
// 	}
}
