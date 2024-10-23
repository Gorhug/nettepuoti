<?php

/**
 * Based partially on code from other Nette cache storages (SQLiteStorage, MemcachedStorage, Copyright (c) 2004 David Grudl (https://davidgrudl.com))
 * Rest of the code and any bugs: Copyright Ilkka Forsblom
 */

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Caching\Cache;


/**
 * SQLite storage, with locking and (nearly) full functionality in terms of callback support in Nette cache system.
 * Not tested. Not necessarily even sensible.
 */
class LockingSQLiteStorage implements Nette\Caching\Storage, Nette\Caching\BulkReader
{
	use Nette\SmartObject;

	private \PDO $pdo;
    private const SleepLength = 250000;
	private $staleLocks = [];

	public function __construct(string $path)
	{
		if ($path !== ':memory:' && !is_file($path)) {
			touch($path); // ensures ordinary file permissions
		}

		$this->pdo = new \PDO('sqlite:' . $path);
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->pdo->exec('
			PRAGMA foreign_keys = ON;
            PRAGMA journal_mode = WAL;
			CREATE TABLE IF NOT EXISTS cache (
				key BLOB NOT NULL PRIMARY KEY,
				data BLOB NOT NULL,
				expire INTEGER,
				slide INTEGER,
                priority INTEGER,
                callbacks BLOB,
                created_at INTEGER NOT NULL
			);
			CREATE TABLE IF NOT EXISTS tags (
				key BLOB NOT NULL REFERENCES cache ON DELETE CASCADE,
				tag BLOB NOT NULL
			);
            CREATE TABLE IF NOT EXISTS locks (
                key BLOB NOT NULL PRIMARY KEY
            );
            CREATE TABLE IF NOT EXISTS items (
				key BLOB NOT NULL REFERENCES cache ON DELETE CASCADE,
				item BLOB NOT NULL,
                created_at INTEGER
			);
			CREATE INDEX IF NOT EXISTS cache_expire ON cache(expire);
			CREATE INDEX IF NOT EXISTS cache_priority ON cache(priority);
			CREATE INDEX IF NOT EXISTS tags_key ON tags(key);
			CREATE INDEX IF NOT EXISTS tags_tag ON tags(tag);
            CREATE INDEX IF NOT EXISTS items_key ON items(key);
			PRAGMA synchronous = NORMAL;
		');
		register_shutdown_function([$this, 'processTerminatorHandler']);
	}

	public function processTerminatorHandler(): void
    {
        // this logic will be called by Terminator.
		if (!empty($this->staleLocks)) {
			$this->pdo->prepare('DELETE FROM locks WHERE key IN (?' . str_repeat(', ?', count($this->staleLocks) - 1) . ')')->execute(array_keys($this->staleLocks));
		}
		$this->pdo->exec('PRAGMA optimize');
    }

	public function read(string $key): mixed
	{
        $readLock = $this->pdo->prepare('SELECT COUNT(*) FROM locks WHERE key = ?');
        $readLock->execute([$key]);
        $locked = $readLock->fetchColumn();
        while ($locked) {
            usleep(self::SleepLength);
            $readLock->execute([$key]);
            $locked = $readLock->fetchColumn();
        }
		$stmt = $this->pdo->prepare('SELECT data, slide, callbacks FROM cache WHERE key=? AND (expire IS NULL OR expire >= ?)');
		$stmt->execute([$key, time()]);
		if (!$row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return null;
		}
		if (!$this->verify($key, $row['callbacks'])) {
			$this->pdo->prepare('DELETE FROM cache WHERE key = ?')->execute([$key]);
			return null;
		}
		if ($row['slide'] !== null) {
			$this->pdo->prepare('UPDATE cache SET expire = ? + slide WHERE key=?')->execute([time(), $key]);
		}

		return unserialize($row['data']);
	}


	public function bulkRead(array $keys): array
	{
        $readLock = $this->pdo->prepare('SELECT COUNT(*) FROM locks WHERE key IN (?' . str_repeat(',?', count($keys) - 1) . ')');
        $readLock->execute($keys);
        $locked = $readLock->fetchColumn();
        while ($locked) {
            usleep(self::SleepLength);
            $readLock->execute($keys);
            $locked = $readLock->fetchColumn();
        }
		$stmt = $this->pdo->prepare('SELECT key, data, slide, callbacks FROM cache WHERE key IN (?' . str_repeat(',?', count($keys) - 1) . ') AND (expire IS NULL OR expire >= ?)');
		$stmt->execute(array_merge($keys, [time()]));
		$result = [];
		$updateSlide = [];
		$deleteKeys = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			if (!$this->verify($row['key'], $row['callbacks'])) {
				$deleteKeys[] = $row['key'];
				continue;
			}
			if ($row['slide'] !== null) {
				$updateSlide[] = $row['key'];
			}
			$result[$row['key']] = unserialize($row['data']);
		}
		if (!empty($deleteKeys)) {
			$this->pdo->prepare('DELETE FROM cache WHERE key IN (?' . str_repeat(', ?', count($deleteKeys) - 1) . ')')->execute($deleteKeys);
		}
		if (!empty($updateSlide)) {
			$stmt = $this->pdo->prepare('UPDATE cache SET expire = ? + slide WHERE key IN(?' . str_repeat(',?', count($updateSlide) - 1) . ')');
			$stmt->execute(array_merge([time()], $updateSlide));
		}

		return $result;
	}


	public function lock(string $key): void
	{
        $locked = false;
        $lockStmt = $this->pdo->prepare('INSERT INTO locks (key) VALUES (?)');
        while (!$locked) {
            try {
                $lockStmt->execute([$key]);
                $locked = true;
            } catch (\PDOException $e) {
                usleep(self::SleepLength);
            }
        }
		$this->staleLocks[$key] = true;
	}

	public function write(string $key, $data, array $dependencies): void
	{
		$expire = isset($dependencies[Cache::Expire])
			? $dependencies[Cache::Expire] + time()
			: null;
		$slide = isset($dependencies[Cache::Sliding])
			? $dependencies[Cache::Expire]
			: null;
        $priority = isset($dependencies[Cache::Priority])
            ? $dependencies[Cache::Priority]
            : null;
        $callbacks = isset($dependencies[Cache::Callbacks])
            ? serialize($dependencies[Cache::Callbacks])
            : null;
        $created_at = hrtime(true);
		$this->pdo->exec('BEGIN TRANSACTION');
		$this->pdo->prepare('REPLACE INTO cache (key, data, expire, slide, priority, callbacks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
			->execute([$key, serialize($data), $expire, $slide, $priority, $callbacks, $created_at]);

		if (!empty($dependencies[Cache::Tags])) {
			foreach ($dependencies[Cache::Tags] as $tag) {
				$arr[] = $key;
				$arr[] = $tag;
			}

			$this->pdo->prepare('INSERT INTO tags (key, tag) SELECT ?, ?' . str_repeat('UNION SELECT ?, ?', count($arr) / 2 - 1))
				->execute($arr);
		}
        if (!empty($dependencies[Cache::Items])) {
            $items = $dependencies[Cache::Items];
            $stmt = $this->pdo->prepare('SELECT key, created_at FROM cache WHERE key IN (?' . str_repeat(',?', count($items) - 1) . ')');
            $stmt->execute($items);
            $arr = [];
            $stmt->fetchAll(\PDO::FETCH_FUNC, function ($item, $created_at) use (&$arr, $key, &$items) {
                $arr[] = $key;
                $arr[] = $item;
                $arr[] = $created_at;
                unset($items[$item]);
            });
            foreach ($items as $item) {
                $arr[] = $key;
                $arr[] = $item;
                $arr[] = null;
            }

            $this->pdo->prepare('INSERT INTO items (key, item, created_at) SELECT ?, ?, ?' . str_repeat('UNION SELECT ?, ?, ?', count($arr) / 3 - 1))
                ->execute($arr);
        }
        $this->pdo->prepare('DELETE FROM locks WHERE key = ?')->execute([$key]);
		$this->pdo->exec('COMMIT');
		unset($this->staleLocks[$key]);
	}

	private function verify(string $key, $callbacks): bool {
		if (!empty($callbacks) && !Cache::checkCallbacks(unserialize($callbacks))) {
			return false;
		}
		$stmt = $this->pdo->prepare('SELECT item, created_at FROM items WHERE key = ?');
		$stmt->execute([$key]);
		$items = [];
		$stmt->fetchAll(\PDO::FETCH_FUNC, function ($item, $created_at) use (&$items) {
			$items[$item] = $created_at;
		});
		if (!empty($items)) {
			$stmt = $this->pdo->prepare('SELECT callbacks, created_at FROM cache WHERE key = ? AND (expire IS NULL OR expire >= ?)');
			$wallClock = time();
			foreach ($items as $depItem => $created_at) {
				$stmt->execute([$depItem, $wallClock]);
				$meta = $stmt->fetch(\PDO::FETCH_ASSOC);
				$time = $meta['created_at'] ?? null;
				$callbacks = $meta['callbacks'] ?? null;
				if ($created_at !== $time || !$this->verify($depItem, $callbacks)) {
					return false;
				}
			}
		}
		return true;
	}
	public function remove(string $key): void
	{
		$this->pdo->exec('BEGIN TRANSACTION');
		$this->pdo->prepare('DELETE FROM cache WHERE key=?')
			->execute([$key]);
        $this->pdo->prepare('DELETE FROM locks WHERE key = ?')->execute([$key]);
		$this->pdo->exec('COMMIT');
		unset($this->staleLocks[$key]);
	}


	public function clean(array $conditions): void
	{
		if (!empty($conditions[Cache::All])) {
			$this->pdo->prepare('DELETE FROM cache')->execute();

		} else {
			$sql = 'DELETE FROM cache WHERE expire < ?';
			$args = [time()];

            if (!empty($conditions[Cache::Priority])) {
                $sql .= ' OR priority < ?';
                $args[] = $conditions[Cache::Priority];
            }

			if (!empty($conditions[Cache::Tags])) {
				$tags = $conditions[Cache::Tags];
				$sql .= ' OR key IN (SELECT key FROM tags WHERE tag IN (?' . str_repeat(',?', count($tags) - 1) . '))';
				$args = array_merge($args, $tags);
			}

			$this->pdo->prepare($sql)->execute($args);
		}
	}
}
