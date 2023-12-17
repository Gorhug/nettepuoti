<?php

declare(strict_types=1);

namespace Naja\Guide\Application\UI\Presenters;


use Nette\Application\UI\Presenter;


abstract class BasePresenter extends Presenter
{
    protected function beforeRender(): void
    {
        parent::beforeRender();
        $this->redrawControl('title');
        $this->redrawControl('content');
    }
}