<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';


$container = App\Bootstrap::boot()
	->createContainer();

// if (!isset($argv[3])) {
// 	echo '
// Add new user to database.

// Usage: create-user.php <name> <email> <password>
// ';
// 	exit(1);
// }

// [, $name, $email, $password] = $argv;

$manager = $container->getByType(App\Model\SpotPriceFacade::class);

try {
	$manager->updateSpotPrices();
	// echo "Prices updated\n";

} catch (Exception $e) {
	echo $e->getMessage() . "\n";
	exit(1);
}
