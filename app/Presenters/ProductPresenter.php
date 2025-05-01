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
namespace App\Presenters;

use App\Model\ActivityPubFacade;
use App\Settings;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Database\Explorer;
use Nette\Http\IResponse;

final class ProductPresenter extends BasePresenter
{
	public function __construct(
		private Explorer $database,
		private Translator $translator,
		private Settings $settings,
		private ActivityPubFacade $activityPubFacade
	) {
		/** Okay, so a lot of sqlite test code is here. Why? 🤷🏼 */
		
		// bdump($database->query('PRAGMA synchronous')->fetch()['synchronous'], "sql_synced");
		// bdump($database->query('PRAGMA busy_timeout')->fetch()["timeout"], "sql_timeout");
		// $query = <<<QUERY
		// SELECT jsonb('{"id": "ascii stuff" }') AS js;
		// QUERY;
		// bdump($database->query($query)->fetch()["js"], "jsonb_test");
		// $values = [
		// 	"recipient_id" => 1,
		// 	"message_json" => $this->database::literal('jsonb(?)', '{"id": "testi"}'),
		// 	"verified" => false,
		// ];
		// bdump($database->table('ap_inbox')->insert($values), "ap_inbox primary");
		// 
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
			->select('id, ?name AS name, ?name AS description, ?name AS brief, created_at, owner, ap_guid', $name, $description, $brief)
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
				->where('owner', $user->getId())
				->order("id DESC");
		}
		$owner = $product->ref('users', 'owner');
		$owner_name = $owner->realname ?? $owner->username ?? '?';
		$this->template->owner = $owner_name;
	}

	public function handleGallery(int $id): void
	{
		$user = $this->getUser();
		if (!$user->isAllowed('media')) {
			$this->error($this->translator->translate('g.media.notAllowed'), IResponse::S403_Forbidden);
		}
		$user_id = $user->getId();
		$product = $this->database->table('products')->get($id);
		if (!$product) {
			$this->error($this->translator->translate('g.edit.notFound'));
		} elseif ($product->owner !== $user_id) {
			$this->error($this->translator->translate('g.edit.notOwner'), IResponse::S403_Forbidden);
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

	public function handlePublish(int $id): void
	{
		$user = $this->getUser();
		if (!$user->isAllowed('activitypub')) {
			$this->error($this->translator->translate('g.dashboard.apNotAllowed'), IResponse::S403_Forbidden);
		}
		$user_id = $user->getId();
		$product = $this->database->table('products')->get($id);
		if (!$product) {
			$this->error($this->translator->translate('g.edit.notFound'));
		} elseif ($product->owner !== $user_id) {
			$this->error($this->translator->translate('g.edit.notOwner'), IResponse::S403_Forbidden);
		}
		$this->activityPubFacade->createFromProduct($product, $user_id, $user->getIdentity()->username, $this->getHttpRequest()->getUrl());
		$this->flashMessage($this->translator->translate('g.product.publishedUpdated'), 'alert-success');
		$this->redirect('Product:show', ['id' => $id]);
	}

}
