<?php

namespace App\Model;

use Nette\Application\LinkGenerator;
use Nette\Http\Request;

final class ActivityPubFacade
{
    public function __construct(
        private \Nette\Database\Explorer $database,
        private \App\Settings $settings,
        private LinkGenerator $lg,
        // private UserFacade $userFacade,
        // private Request $request
    ) {
    }

    public function createKeys($id)
    {
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
            'private_key' => $private_key_pem,
            'keys_created_at' => new \DateTimeImmutable(),
        ]);
    }

    public function getPublicKey($id)
    {
        $user = $this->database->table('users')->get($id);
        if (!$user) {
            throw new \Exception("no user found");
        }
        return $user->public_key;
    }

    public function webfinger($username, $server, $scheme)
    {
        // global $username, $server;
        // $server = $_SERVER["SERVER_NAME"];

        $webfinger = array(
            "subject" => "acct:{$username}@{$server}",
            "links" => array(
                array(
                    "rel" => "self",
                    "type" => "application/activity+json",
                    "href" => $this->lg->link("Pub:user", ["username" => $username]),
                )
            )
        );
        // header( "Content-Type: application/json" );
        return $webfinger;
    }
    public function username($user_id, $username, $server) {
		// global $username, $realName, $summary, $server, $key_public;
        $user = $this->database->table('users')->get($user_id);
        $img = $user->ref('images','avatar')->filename ?? 'avatar.webp';
		$user = array(
			"@context" => [
				"https://www.w3.org/ns/activitystreams",
				"https://w3id.org/security/v1"
			],
			                       "id" => "https://{$server}/user/{$username}",
			                     "type" => "Person",
			                "following" => "https://{$server}/following/{$username}",
			                "followers" => "https://{$server}/followers/{$username}",
			                    "inbox" => "https://{$server}/inbox/{$username}",
			                   "outbox" => "https://{$server}/outbox/{$username}",
			        "preferredUsername" =>  $username, //rawurldecode( $username ),
			                     "name" => "{$user->realname}",
			                  "summary" => "{$user->bio}", //TODO: Markdown render
			                      "url" => "https://{$server}/user/{$username}",
			"manuallyApprovesFollowers" =>  false,
			             "discoverable" =>  true,
			                "published" => $user->keys_created_at,
			"icon" => [
				     "type" => "Image",
				"mediaType" => "image/png",
				      "url" => "https://{$server}/{$this->settings->uploadDir}/{$img}"
			],
			"image" => [
				     "type" => "Image",
				"mediaType" => "image/png",
				      "url" => "https://{$server}/banner.png"
			],
			"publicKey" => [
				"id"           => "https://{$server}/{$username}#main-key",
				"owner"        => "https://{$server}/user/{$username}",
				"publicKeyPem" => $user->public_key,
			]
		);
		// header( "Content-Type: application/activity+json" );
		return $user;
	}

}