<?php
namespace App\Presenters;

use App\Settings;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

final class ProductPresenter extends BasePresenter
{
	public function __construct(
		private Nette\Database\Explorer $database,
		private Translator $translator,
		private Settings $settings
	) {
	}

	public function renderShow(int $id): void
	{
		$name = 'name';
        $description = 'description';
		$brief = 'brief';
        if ($this->locale === 'fi') {
            $name = 'name_fi';
            $description = 'description_fi';
			$brief = 'brief_fi';
		}
		$product = $this->database
			->table('products')
			->select('id, ?name AS name, ?name AS description, ?name AS brief, created_at', $name, $description, $brief)
			->get($id);

		if (!$product) {
			$this->error($this->translator->translate('g.edit.notFound'));
		}

		$this->template->product = $product;

		$this->template->uploadDir = $this->settings->uploadDir;
		$checked = [];
		foreach($this->database
			->table('product_gallery')
			->where('product', $id) as $row) {
				$checked[] = $row->image;
		}
		$this->template->checked = $checked;
		$this->template->gallery = $this->database
			->table('images')
			// ->where('owner', $this->getUser()->getId())
			->where('id', $checked);

		$user = $this->getUser();
		if ($user->isAllowed('media')) {
			$this->template->images = $this->database
				->table('images')
				->where('owner', $user->getId());
		}
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
