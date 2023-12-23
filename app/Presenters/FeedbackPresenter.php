<?php

namespace App\Presenters;
use App\Model\ContactFacade;
use Nette\Application\UI\Form;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use App\Forms\FormFactory;
use stdClass;

class FeedbackPresenter extends BasePresenter
{
	public function __construct(
		private ContactFacade $facade,
        private FormFactory $formFactory,
	) {
	}

	protected function createComponentContactForm(): Form
	{
		// ...
        $form = $this->formFactory->create();
		$form->addText('name', 'Name:')
			->setRequired('Enter your name');
		$form->addEmail('email', 'E-mail:')
			->setRequired('Enter your e-mail');
		$form->addTextarea('message', 'Message:')
			->setRequired('Enter message');
		$form->addSubmit('send', 'Send');
		$form->onSuccess[] = [$this, 'contactFormSucceeded'];
		return $form;
	}

	public function contactFormSucceeded(stdClass $data): void
	{
		$this->facade->sendMessage($data->email, $data->name, $data->message);
		$this->flashMessage('The message has been sent', 'alert-success');
		$this->redirect('this');
	}

}
