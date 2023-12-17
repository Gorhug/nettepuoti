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

	public function renderShow(int $productId): void
	{
		$product = $this->database
			->table('products')
			->get($productId);

		if (!$product) {
			$this->error('Product not found');
		}

		$this->template->product = $product;
	}
}
