<?php

namespace App\Model;
final class ActivityPubFacade
{
	public function __construct(
		private \Nette\Database\Explorer $database,
        private \App\Settings $settings
	) {
	}

    public function createKeys($id) {
        $user = $this->database->table('users')->get($id);
        if (!$user) {
            throw new \Exception("no user found");
        }
        if ($user->public_key) {
            throw new \Exception("keys already exist");
        }

        $private_key = openssl_pkey_new();
        $public_key_pem = openssl_pkey_get_details($private_key)['key'];
        $private_key_pem = null;
        openssl_pkey_export($private_key, $private_key_pem);
        $user->update([
            'public_key' => $public_key_pem,
            'private_key' => $private_key_pem
        ]);
    }

    public function getPublicKey($id) {
        $user = $this->database->table('users')->get($id);
        if (!$user) {
            throw new \Exception("no user found");
        }
        return $user->public_key;
    }
}