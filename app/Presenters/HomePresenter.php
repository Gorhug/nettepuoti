<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private Nette\Database\Explorer $database,
    ) {
    }
    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->redrawControl('title');
        $this->redrawControl('content');
        // $this['basketWidget']->redrawControl();
    }

    public function renderDefault(): void
    {
        $this->template->products = $this->database
            ->table('products')
            ->order('created_at DESC')
            ->limit(5);
    }

}
