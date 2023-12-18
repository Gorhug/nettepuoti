<?php

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
        $this->template->products = $this->facade
            ->getPublicProducts()
            ->limit(5);
    }

}
