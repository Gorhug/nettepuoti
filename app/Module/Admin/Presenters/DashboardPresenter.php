<?php

declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Ublaboo\DataGrid\DataGrid;

/**
 * Presenter for the dashboard view.
 * Ensures the user is logged in before access.
 */
final class DashboardPresenter extends BasePresenter
{
	// Incorporates methods to check user login status
	use RequireLoggedUser;
	public function __construct(
		private \App\Model\UserFacade $userFacade,
		private \App\Model\ActivityPubFacade $apFacade,
		private \Nette\Localization\Translator $translator
	) {
	}
	public function createComponentSimpleGrid($name)
	{
		$grid = new DataGrid($this, $name);

		$grid->setDataSource($this->userFacade->getDataSource());
		$grid->addColumnText('username', 'Username')
			->setSortable()
			->setFilterText();
		$grid->addColumnText('email', 'Email')
			->setSortable()
			->setFilterText();
		$grid->addColumnText('role', 'Role')
			->setSortable()
			->setFilterText();
	}
	public function handleKeys()
	{
		$user = $this->getUser();
		if (!$user->isAllowed('activitypub')) {
			$this->error($this->translator->translate('g.ap.notAllowed'), \Nette\Http\IResponse::S403_Forbidden);
		}
		$this->apFacade->createKeys($user->getIdentity()->getId());
		$this->flashMessage($this->translator->translate('g.ap.keysCreated'), 'alert-success');
		$this->redirect('this');
	}

	public function renderDefault(): void
	{
		$user = $this->getUser();
		$this->template->public_key = $this->apFacade->getPublicKey($user->getIdentity()->getId());
	}
}
