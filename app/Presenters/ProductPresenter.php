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
		$name = 'name';
        $description = 'description';
        if ($this->locale === 'fi') {
            $name = 'name_fi';
            $description = 'description_fi';
		}
		$product = $this->database
			->table('products')
			->select('id, ?name AS name, ?name AS description, created_at', $name, $description)
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
