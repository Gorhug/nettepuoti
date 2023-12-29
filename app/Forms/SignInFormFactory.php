<?php

declare(strict_types=1);

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\User;
use Nette\Localization\Translator;

/**
 * Factory for creating sign-in forms with authentication logic.
 */
final class SignInFormFactory
{
	// Dependency injection of form factory and current user session
	public function __construct(
		private FormFactory $factory,
		private User $user,
		private Translator $translator,
	) {
	}


	/**
	 * Create a sign-in form with fields for username, password, and a "remember me" option.
	 * Contains logic to handle successful form submissions.
	 */
	public function create(callable $onSuccess): Form
	{
		$form = $this->factory->create();
		$form->setTranslator($this->translator);
		$form->addText('username', 'g.form.username')
			->setRequired('g.form.usernameRequired');

		$form->addPassword('password', 'g.form.password')
			->setRequired('g.form.passwordRequired');

		$form->addCheckbox('remember', 'g.form.remember');

		$form->addSubmit('send', 'g.form.sendSignIn');

		// Handle form submission
		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess): void {
			try {
				// Attempt to login user
				$this->user->setExpiration($data->remember ? '14 days' : '20 minutes');
				$this->user->login($data->username, $data->password);
			} catch (Nette\Security\AuthenticationException $e) {
				$form->addError('g.form.errorSignIn');
				return;
			}
			$onSuccess();
		};

		return $form;
	}
}
