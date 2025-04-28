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
namespace App\Presenters;
use App\Model\ContactFacade;
use Nette\Application\UI\Form;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use App\Forms\FormFactory;
use stdClass;
use Nette\Localization\Translator;

class FeedbackPresenter extends BasePresenter
{
	public function __construct(
		private ContactFacade $facade,
        private FormFactory $formFactory,
		private Translator $translator,
	) {
	}

	protected function createComponentContactForm(): Form
	{
		// ...
        $form = $this->formFactory->create();
		$form->setTranslator($this->translator);
		$form->addText('name', 'g.feedback.name')
			->setRequired('g.feedback.nameRequired');
		$form->addEmail('email', 'g.feedback.email')
			->setRequired('g.feedback.emailRequired');
		$form->addTextarea('message', 'g.feedback.message')
			->setRequired('g.feedback.messageRequired');
		$form->addSubmit('send', 'g.feedback.send');
		$form->onSuccess[] = [$this, 'contactFormSucceeded'];
		return $form;
	}

	public function contactFormSucceeded(stdClass $data): void
	{
		$this->facade->sendMessage($data->email, $data->name, $data->message);
		$this->flashMessage('g.feedback.sent', 'alert-success');
		$this->redirect('this');
	}

}
