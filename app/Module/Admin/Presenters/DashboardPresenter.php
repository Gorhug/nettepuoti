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
}
