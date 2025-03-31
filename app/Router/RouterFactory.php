<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;
		$router->addRoute('.well-known/webfinger', 'Pub:webfinger');
		$router->addRoute('user/<user>', 'Pub:user');
		$router->addRoute('<locale=en (fi|en)>/manage/<presenter>/<action>', [
			'module' => 'Admin',
		]);
		$router->addRoute('<locale=en (fi|en)>/<presenter>/<action>[/<id>]', 'Home:default');
		return $router;
	}
}
