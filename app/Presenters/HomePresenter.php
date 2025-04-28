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
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;


final class HomePresenter extends BasePresenter
{
    public function __construct(
        private ProductFacade $facade,
    ) {
    }

    public function renderDefault(): void
    {
        $name = 'name';
        $description = 'description';
        $brief = 'brief';
        if ($this->locale === 'fi') {
            $name = 'name_fi';
            $description = 'description_fi';
            $brief = 'brief_fi';
        }
        $this->template->products = $this->facade
            ->getPublicProducts()
            ->limit(5)
            ->select('id, ?name AS name, ?name AS brief, ?name AS description, created_at', $name, $brief, $description);
    }

    public function renderRss(): void
    {
        $this->renderDefault();
        $this->setLayout(false);
        $httpResponse = $this->getHttpResponse();
        $httpResponse->setContentType("application/rss+xml", "utf-8");
    }
}
