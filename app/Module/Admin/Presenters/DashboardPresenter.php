<?php

declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;


/**
 * Presenter for the dashboard view.
 * Ensures the user is logged in before access.
 */
final class DashboardPresenter extends BasePresenter
{
	// Incorporates methods to check user login status
	use RequireLoggedUser;
}
