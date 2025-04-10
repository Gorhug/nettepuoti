<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


/**
 * Handles 4xx HTTP error responses.
 */
final class Error4xxPresenter extends Nette\Application\UI\Presenter
{
	protected function checkHttpMethod(): void
	{
		// allow access via all HTTP methods and ensure the request is a forward (internal redirect)
		if (!$this->getRequest()->isMethod(Nette\Application\Request::FORWARD)) {
			$this->error();
		}
	}


	public function renderDefault(Nette\Application\BadRequestException $exception): void
	{
		// if the Presenter said it's responding in json, send json
		if (preg_match('#^application/json(?:;|$)#', (string) $this->getHttpResponse()->getHeader('Content-Type'))) {
			$this->sendJson(["error" => $exception->getMessage()]);
		}
		// renders the appropriate error template based on the HTTP status code
		$code = $exception->getCode();
		$file = is_file($file = __DIR__ . "/templates/Error/$code.latte")
			? $file
			: __DIR__ . '/templates/Error/4xx.latte';
		$this->template->httpCode = $code;
		$this->template->message = $exception->getMessage();
		$this->template->setFile($file);
	}
}
