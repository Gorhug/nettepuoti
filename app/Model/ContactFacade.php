<?php
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
