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
        $latte = $this->template->getLatte();
        $latte->setLocale($fullLoc);
    }
}