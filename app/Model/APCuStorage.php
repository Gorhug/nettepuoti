<?php

/**
 * APCu storage for Nette cache. Partially based on from MemcacheStorage, Copyright (c) 2004 David Grudl (https://davidgrudl.com). 
 * Rest of the code and any bugs: Copyright Ilkka Forsblom
 * DO NOT USE. UNTESTED.
 */

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Caching\Cache;


/**
 * APCu storage using apcu extension.
 */
class APCuStorage implements Nette\Caching\Storage, Nette\Caching\BulkReader
{
	/** @internal cache structure */
	private const
		MetaCallbacks = 'callbacks',
		MetaData = 'data',
		MetaDelta = 'delta',
		MetaItems = 'di',
		MetaTime = 'time';


	/**
	 * Checks if Memcached extension is available.
	 */
	public static function isAvailable(): bool
	{
		return extension_loaded('apcu') && apcu_enabled();
	}


	public function __construct(

	) {
		if (!static::isAvailable()) {
			throw new Nette\NotSupportedException("PHP extension 'apcu' is not loaded or enabled.");
		}
	}

	private function verify(string $key, $meta): bool {
		if (!empty($meta[self::MetaCallbacks]) && !Cache::checkCallbacks($meta[self::MetaCallbacks])) {
			apcu_delete($key);
			return false;
		}
		if (!empty($meta[self::MetaItems])) {
			$diMetas = apcu_fetch(array_keys($meta[self::MetaItems]));
			foreach ($meta[self::MetaItems] as $depItem => $time) {
				if (($diMetas[$depItem][self::MetaTime] ?? null) !== $time || (!empty($diMetas[$depItem]) && !$this->verify($depItem, $diMetas[$depItem]))) {
					apcu_delete($key);
					return false;
				}
			}
		}
		// add tag check here
		if (!empty($meta[self::MetaDelta])) {
			apcu_store($key, $meta, $meta[self::MetaDelta]);
		}
		return true;
	}

	public function read(string $key): mixed
	{
		while (apcu_exists($key . '_lock')) {
			usleep(250000);
		}
		$meta = apcu_fetch($key);
		if (!$meta) {
			return null;
		}

		// meta structure:
		// array(
		//     data => stored data
		//     delta => relative (sliding) expiration
		//     callbacks => array of callbacks (function, args)
		// )

		// verify dependencies
		if (!$this->verify($key, $meta)) {
			return null;
		}

		return $meta[self::MetaData];
	}


	public function lock(string $key): void
	{
		while (!apcu_add($key . '_lock', true, 10)) {
			usleep(250000);
		}
	}
	


	public function write(string $key, $data, array $dp): void
	{
		$time = hrtime(true);
		$meta = [
			self::MetaData => $data,
			self::MetaTime => $time,
		];

		$expire = 0;
		if (isset($dp[Cache::Expire])) {
			$expire = (int) $dp[Cache::Expire];
			if (!empty($dp[Cache::Sliding])) {
				$meta[self::MetaDelta] = $expire; // sliding time
			}
		}

		if (isset($dp[Cache::Callbacks])) {
			$meta[self::MetaCallbacks] = $dp[Cache::Callbacks];
		}
		$depItems = $dp[Cache::Items] ?? [];
		if (isset($dp[Cache::Tags])) {
			$tagKeys = array_map(function ($tag) {
				return 'tag:' . $tag;
			}, $dp[Cache::Tags]);
			foreach ($tagKeys as $tagKey) {
				$m = apcu_fetch($tagKey);
				if (!$m) {
					$m = [self::MetaTime => $time];
					apcu_store($tagKey, $m, 0);
				}
				$meta[self::MetaItems][$tagKey] = $m[self::MetaTime];
			}
		}
		if ($depItems) {
			$diMetas = apcu_fetch($depItems);
			foreach ($depItems as $item) {
				$meta[self::MetaItems][$item] = $diMetas[$item][self::MetaTime] ?? null;
			}
		}



		apcu_store($key, $meta, $expire);
		apcu_delete($key . '_lock');
	}


	public function remove(string $key): void
	{
		apcu_delete([$key, $key . '_lock']);
	}

	public function clean(array $conditions): void
	{
		if (!empty($conditions[Cache::All])) {
			apcu_clear_cache();

		} else if (!empty($conditions[Cache::Tags])) {
			$tagKeys = array_map(function ($tag) {
				return 'tag:' . $tag;
			}, $conditions[Cache::Tags]);
			apcu_delete($tagKeys);
		}

	}

	public function bulkRead(array $keys): array
	{
		$locks = array_map(function ($key) {
			return $key . '_lock';
		}, $keys);
		while (apcu_exists($locks)) {
			usleep(250000);
		}
		$metas = apcu_fetch($keys);
		$result = [];
		foreach ($metas as $key => $meta) {
			if (self::verify($key, $meta)) {
				$result[$key] = $meta[self::MetaData];
			}
		}

		return $result;
	}
}