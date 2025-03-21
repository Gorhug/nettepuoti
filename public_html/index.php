<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$configurator = App\Bootstrap::boot();
$container = $configurator->createContainer();
$conn = $container->getByType(Nette\Database\Connection::class);
$conn->query('PRAGMA synchronous=NORMAL');
$application = $container->getByType(Nette\Application\Application::class);
$application->run();
