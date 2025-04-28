<?php
/*
Copyright Ilkka Forsblom.

This file is part of Nettepuoti.

Nettepuoti is free software: you can redistribute it and/or modify 
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the 
License, or (at your option) any later version.

Nettepuoti is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License 
along with Nettepuoti. If not, see <https://www.gnu.org/licenses/>. 
*/
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Nette\Security\IIdentity;

/**
 * Manages user-related operations such as authentication and adding new users.
 */
final class UserFacade implements Nette\Security\Authenticator, Nette\Security\IdentityHandler
{
	// Minimum password length requirement for users
	public const PasswordMinLength = 7;

	// Database table and column names
	private const
		TableName = 'users',
		ColumnId = 'id',
		ColumnName = 'username',
		ColumnPasswordHash = 'password',
		ColumnEmail = 'email',
		ColumnRole = 'role',
		ColumnToken = 'authtoken';

	// Dependency injection of database explorer and password utilities
	public function __construct(
		private Nette\Database\Explorer $database,
		private Passwords $passwords,
	) {
	}


	/**
	 * Authenticate a user based on provided credentials.
	 * Throws an AuthenticationException if authentication fails.
	 */
	public function authenticate(string $username, #[\SensitiveParameter] string $password): Nette\Security\SimpleIdentity
	{
		// Fetch the user details from the database by username
		$row = $this->database->table(self::TableName)
			->where(self::ColumnName, $username)
			->fetch();

		// Authentication checks
		if (!$row) {
			throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);

		} elseif (!$this->passwords->verify($password, $row[self::ColumnPasswordHash])) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);

		} elseif ($this->passwords->needsRehash($row[self::ColumnPasswordHash])) {
			$row->update([
				self::ColumnPasswordHash => $this->passwords->hash($password),
			]);
		}

		// Return user identity without the password hash
		$arr = $row->toArray();
		unset($arr[self::ColumnPasswordHash]);
		return new Nette\Security\SimpleIdentity($row[self::ColumnId], $row[self::ColumnRole], $arr);
	}


	/**
	 * Add a new user to the database.
	 * Throws a DuplicateNameException if the username is already taken.
	 */
	public function add(string $username, string $email, #[\SensitiveParameter] string $password): void
	{
		// Validate the email format
		Nette\Utils\Validators::assert($email, 'email');

		// Attempt to insert the new user into the database
		try {
			$this->database->table(self::TableName)->insert([
				self::ColumnName => $username,
				self::ColumnPasswordHash => $this->passwords->hash($password),
				self::ColumnEmail => $email,
				self::ColumnToken => Nette\Utils\Random::generate(16),
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateNameException;
		}
	}

	public function changeRole(string $username, string $role): void
	{
		$this->database->table(self::TableName)
			->where(self::ColumnName, $username)
			->update([self::ColumnRole => $role]);
	}

	public function getDataSource(): Nette\Database\Table\Selection
	{
		return $this->database->table(self::TableName);
	}

	public function sleepIdentity(IIdentity $identity): SimpleIdentity
	{
		// we return a proxy identity, where in the ID is authtoken
		return new SimpleIdentity(($identity->getData())[self::ColumnToken]);
	}

	public function wakeupIdentity(IIdentity $identity): ?SimpleIdentity
	{
		// replace the proxy identity with a full identity, as in authenticate()
		// $row = $this->database->fetch('SELECT * FROM user WHERE authtoken = ?', $identity->getId());
		$row = $this->database->table(self::TableName)
		->where(self::ColumnToken, $identity->getId())
		->select(join(',', [self::ColumnId, self::ColumnName, self::ColumnEmail, self::ColumnRole, self::ColumnToken, 'realname', 'bio', 'bio_fi']))
		->fetch();
		return $row
			? new SimpleIdentity($row[self::ColumnId], $row[self::ColumnRole], $row->toArray())
			: null;
	}

	public function updateDetails($id, FormData\Details $details): void
	{
		$this->database->table(self::TableName)
			->get($id)
			->update([
				"realname" => $details->realname,
				"bio" => $details->bio,
				"bio_fi" => $details->bio_fi
			]);
	}
	public function getDetails($id): FormData\Details
	{
		$row = $this->database->table(self::TableName)
			->get($id);
		return new FormData\Details($row->realname, $row->bio, $row->bio_fi);
	}

	public function getAvatar($id): Nette\Database\Table\ActiveRow|null
	{
		$row = $this->database->table(self::TableName)
			->get($id)->ref('images', 'avatar');
		return $row;
	}

	public function getId($username) {
		$row = $this->database->table(self::TableName)
			->where(self::ColumnName, $username)
			->fetch();
		return $row?->id;
	}

	public function getApId($username) {
		$row = $this->database->table(self::TableName)
			->where(self::ColumnName, $username)
			->fetch();
		if (!$row || !$row->keys_created_at) {
			return null;
		}
		return $row->id;
	}
}


/**
 * Custom exception for duplicate usernames.
 */
class DuplicateNameException extends \Exception
{
}
