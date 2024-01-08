<?php

declare(strict_types=1);

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Http\Session;

/**
 * Factory for creating general forms with optional CSRF protection.
 */
final class FormFactory
{
	// Dependency injection of the current user session
	public function __construct(
		// private Nette\Security\User $user,
		private Session $session,
	) {
	}


	/**
	 * Create a new form instance. If user is logged in, add CSRF protection.
	 */
	public function create(): Form
	{
		$form = new Form;
		if ($this->session->isStarted()) {
		// if ($this->user->isLoggedIn()) {
			$form->addProtection();
		}
		return $form;
	}
}
