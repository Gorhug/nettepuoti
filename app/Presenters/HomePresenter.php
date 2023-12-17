<?php

declare(strict_types=1);

namespace App\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;


final class HomePresenter extends BasePresenter
{
    public function __construct(
        private Nette\Database\Explorer $database,
    ) {
    }

    public function renderDefault(): void
    {
        $this->template->products = $this->database
            ->table('products')
            ->order('created_at DESC')
            ->limit(5);
    }

}
