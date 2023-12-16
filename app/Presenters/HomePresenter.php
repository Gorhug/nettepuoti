<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->redrawControl('title');
        $this->redrawControl('content');
        // $this['basketWidget']->redrawControl();
    }
}
