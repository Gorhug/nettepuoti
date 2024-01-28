<?php

declare(strict_types=1);

namespace Naja\Guide\Application\UI\Presenters;


use Nette\Application\UI\Presenter;
use Nette\Application\Attributes\Persistent;
use IntlDateFormatter;

abstract class BasePresenter extends Presenter
{
    #[Persistent]
    public string $locale; // must be public
    protected function beforeRender(): void
    {
        parent::beforeRender();
        $this->redrawControl('title');
        $this->redrawControl('content');
        $this->template->locale = $this->locale;
        $fullLoc = $this->locale === 'fi' ? 'fi_FI' : 'en_FI';
        $dater = new IntlDateFormatter(
            $fullLoc,
            IntlDateFormatter::RELATIVE_FULL,
            IntlDateFormatter::NONE,
            'Europe/Helsinki',
            IntlDateFormatter::GREGORIAN
        );
        $this->template->addFilter('intlDay', fn($date) => $dater->format($date));
        $hourer = new IntlDateFormatter(
            $fullLoc,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            'Europe/Helsinki',
            IntlDateFormatter::GREGORIAN,
            'HH'
        );
        $this->template->addFilter('intlHour', fn($date) => $hourer->format($date));

        $fullDater = new IntlDateFormatter(
            $fullLoc,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Helsinki',
            IntlDateFormatter::GREGORIAN
        );
        $this->template->addFilter('intlFullDay', fn($date) => $fullDater->format($date));
    }
}