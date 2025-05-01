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

namespace App\Presenters;

use App\Model\ProductFacade;
use App\Model\UserFacade;
use App\Settings;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;


final class KapakkaPresenter extends BasePresenter
{
    public function __construct(
        private UserFacade $users,
        private Settings $settings,
    ) {
    }

    public function renderProfile(string $username) {
		$user_id = $this->users->getId($username);
        if ($user_id === null) {
            $this->error('User not found');
        }
        $this->template->uploadDir = $this->settings->uploadDir;
        $this->template->username = $username;
		$this->template->details = $this->users->getDetails($user_id);
		$this->template->avatar = $this->users->getAvatar($user_id);
	}
}