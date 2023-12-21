<?php
namespace App;

class Settings
{
	public function __construct(
		// since PHP 8.1 it is possible to specify readonly
		// public bool $debugMode,
		// public string $appDir,
		// and so on
        public string $entsoeToken,
	) {}
}
