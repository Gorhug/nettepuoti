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
declare(strict_types=1);

namespace App\Module\Admin\Presenters;


/**
 * Trait to enforce user authentication.
 * Redirects unauthenticated users to the sign-in page.
 */
trait RequireLoggedUser
{
	/**
	 * Injects the requirement for a logged-in user.
	 * Attaches a callback to the startup event of the presenter.
	 */
	public function injectRequireLoggedUser(): void
	{
		$this->onStartup[] = function () {
			// If the user isn't logged in, redirect them to the sign-in page
			if (!$this->getUser()->isLoggedIn()) {
				$this->redirect('Sign:in', ['backlink' => $this->storeRequest()]);
			}
		};
	}
}
