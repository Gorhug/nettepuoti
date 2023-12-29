<?php

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
