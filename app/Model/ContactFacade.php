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

namespace App\Model;

use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Bridges\ApplicationLatte\LatteFactory;

class ContactFacade
{
	public function __construct(
		private Mailer $mailer,
        private LatteFactory $latteFactory,
        private \App\Settings $settings,
	) {
	}

    public function sendMessage(string $email, string $name, string $message): void
	{
		$latte = $this->latteFactory->create();
		$body = $latte->renderToString(__DIR__ . '/contactEmail.latte', [
			'email' => $email,
			'name' => $name,
			'message' => $message,
            'date' => new \DateTimeImmutable('now', new \DateTimeZone("Europe/Helsinki"))
		]);

		$mail = new Message;
        $mail->setFrom($this->settings->botEmail, 'gorhug.fi contact form')
            ->addTo($this->settings->adminEmail, $this->settings->adminName) // your email
			->addReplyTo($email, $name)
			->setHtmlBody($body);

		$this->mailer->send($mail);
	}
}
