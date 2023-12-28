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
		$router->addRoute('<locale=en>/manage/<presenter>/<action>', [
			'module' => 'Admin',
		]);
		$router->addRoute('<locale=en>/<presenter>/<action>[/<id>]', 'Home:default');
		return $router;
	}
}
