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
        $name = 'name';
        $description = 'description';
        if ($this->locale === 'fi') {
            $name = 'name_fi';
            $description = 'description_fi';
        }
        $this->template->products = $this->facade
            ->getPublicProducts()
            ->limit(5)
            ->select('id, ?name AS name, ?name AS description, created_at', $name, $description);
    }

}
