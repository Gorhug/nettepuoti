<?php

/**
 * An attempt at APCu lockin for SQLite cache storage in Nette. 
 * DO NOT USE. UNTESTED.
 */

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Caching\Cache;


/**
 * Memcached storage using memcached extension.
 */
class EnhancedSQLiteStorage extends Nette\Caching\Storages\SQLiteStorage implements Nette\Caching\Storage, Nette\Caching\BulkReader
{

	private bool $locking = false;
  
    /**  for usleep() */
    private const SleepLength = 250000;

	/**
	 * Checks if apcu extension is available.
	 */
	public static function apcuAvailable(): bool
	{
		return extension_loaded('apcu') && apcu_enabled();
	}

	public function __construct(
        string $path
	) {
		$this->locking = static::apcuAvailable();
        parent::__construct($path);
	}

    public function read(string $key): mixed
	{
        if ($this->locking) {
		    while (apcu_exists($key . '_lock')) {
			    usleep(self::SleepLength);
		    }
        }

		return parent::read($key);
	}

	public function lock(string $key): void
	{
        dump([$key, $this->locking]);
        if (!$this->locking) {
			return;
		}
		while (!apcu_add($key . '_lock', true, 10)) {
			usleep(self::SleepLength);
		}
	}

    public function write(string $key, $data, array $dp): void
	{
		parent::write($key, $data, $dp);
        // dump([$key, $this->locking]);
        if ($this->locking) {
            // dump(apcu_fetch($key . '_lock'));
		    apcu_delete($key . '_lock');
        }
    }

    public function remove(string $key): void
	{
        parent::remove($key);
        if ($this->locking) {
		    apcu_delete($key . '_lock');
        }
    }

    public function bulkRead(array $keys): array
    {
        if ($this->locking) {
            $locks = array_map(function ($key) {
                return $key . '_lock';
            }, $keys);
            while (apcu_exists($locks)) {
                usleep(self::SleepLength);
            }
        }
        return parent::bulkRead($keys);
    }


}