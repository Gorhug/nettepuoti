<?php

declare(strict_types=1);

namespace Naja\Guide\Application\UI\Presenters;


use Nette\Application\UI\Presenter;
use Nette\Application\Attributes\Persistent;

abstract class BasePresenter extends Presenter
{
    #[Persistent]
	public string $locale; // must be public
    protected function beforeRender(): void
    {
        parent::beforeRender();
        $this->redrawControl('title');
        $this->redrawControl('content');
    }
}