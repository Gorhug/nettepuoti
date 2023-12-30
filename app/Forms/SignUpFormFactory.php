<?php

declare(strict_types=1);

namespace App\Forms;

use App\Model;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

/**
 * Factory for creating user sign-up forms with registration logic.
 */
final class SignUpFormFactory
{
	// Dependency injection of form factory and user management facade
	public function __construct(
		private FormFactory $factory,
		private Model\UserFacade $userFacade,
		private Translator $translator,
	) {
	}


	/**
	 * Create a sign-up form with fields for username, email, and password.
	 * Contains logic to handle successful form submissions.
	 */
	public function create(callable $onSuccess): Form
	{
		$form = $this->factory->create();
		$form->setTranslator($this->translator);
		$form->addText('username', 'g.form.upUsername')
			->setRequired('g.form.upUsernameRequired');

		$form->addEmail('email', 'g.form.upEmail')
			->setRequired('g.form.upEmailRequired');

		$form->addPassword('password', 'g.form.upPassword')
			->setOption('description', sprintf($this->translator->translate('g.form.upPasswordDescription'), $this->userFacade::PasswordMinLength))
			->setRequired('g.form.upPasswordRequired')
			->setHtmlAttribute('minLength', $this->userFacade::PasswordMinLength)
			->addRule($form::MIN_LENGTH, 'g.form.upPassLength', $this->userFacade::PasswordMinLength);

		$form->addSubmit('send', 'g.form.sendSignUp');

		// Handle form submission
		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess): void {
			try {
				// Attempt to register a new user
				$this->userFacade->add($data->username, $data->email, $data->password);
			} catch (Model\DuplicateNameException $e) {
				// Handle the case where the username is already taken
				$form['username']->addError('g.form.upTaken');
				return;
			}
			$onSuccess();
		};

		return $form;
	}
}
