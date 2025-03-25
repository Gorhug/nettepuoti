<?php
namespace App\Presenters;

use App\Settings;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Database\Explorer;

final class ProductPresenter extends BasePresenter
{
	public function __construct(
		private Explorer $database,
		private Translator $translator,
		private Settings $settings
	) {
		// bdump($database->getConnection()->query('PRAGMA synchronous')->fetch()['synchronous'], "sql_synced");
		// bdump($database->getConnection()->query('PRAGMA busy_timeout')->fetch()["timeout"], "sql_timeout");
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
		foreach ($this->database->table('product_gallery')->where('product', $id) as $row) {
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

	public function handleGallery(int $id): void
	{
		$user = $this->getUser();
		if (!$user->isAllowed('media')) {
			$this->error($this->translator->translate('g.media.notAllowed'), \Nette\Http\IResponse::S403_Forbidden);
		}
		$gallery = $this->getHttpRequest()->getPost('gallery') ?? [];
		// dump($gallery);
		$images = [];
		foreach ($gallery as $img) {
			$images[] = ['product' => $id, 'image' => $img];
		}
		$this->database->transaction(function (Explorer $db) use ($id, $images) {
			$db->table('product_gallery')
				->where('product', $id)
				->delete();
			if (empty($images)) {
				return; // exit transaction
			}
			$db->table('product_gallery')
				->insert($images);
		});
		$this->flashMessage($this->translator->translate('g.product.galleryUpdated'), 'alert-success');
		$this->redirect('Product:show', ['id' => $id]);
	}

}
