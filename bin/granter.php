<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';


$container = App\Bootstrap::boot()
	->createContainer();

if (!isset($argv[2])) {
	echo '
Manage a users role

Usage: granter.php username role
';
	exit(1);
}

[, $name, $role] = $argv;

$manager = $container->getByType(App\Model\UserFacade::class);

try {
	$manager->changeRole($name, $role);
	echo "User $name role is now $role.\n";

} catch (\Exception $e) {
	exit($e->getMessage());
}
