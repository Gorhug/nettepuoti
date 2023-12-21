<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\SpotPriceFacade;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;


final class SpotPricePresenter extends BasePresenter
{
    public function __construct(
        private SpotPriceFacade $facade,
    ) {
    }

    public function renderDefault(): void
    {
        $this->template->spotPrices = $this->facade
            ->getSpotPrices();
    }

}
