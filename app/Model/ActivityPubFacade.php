<?php

namespace App\Model;

use DateTimeImmutable;
use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Caching\Cache;
use Nette\Database\Table\ActiveRow;
use Nette\Http\UrlImmutable;
use Nette\Http\UrlScript;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\Json;
use Nette\Utils\Random;
use Tracy\Debugger;

final class ActivityPubFacade
{
    private const USERAGENT = "salakapakka/0.1";
    private Cache $cache;
    public \Closure $getCachedJson;
    public function __construct(
        private \Nette\Database\Explorer $database,
        private \App\Settings $settings,
        private LinkGenerator $lg,
        private LatteFactory $latteFactory,
        \Nette\Caching\Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'activitypub');
        $this->getCachedJson = $this->cache->wrap([$this, 'getJsonFromUrl'], [Cache::Expire => '20 minutes']);
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
            'keys_created_at' => new DateTimeImmutable,
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

    public function webfinger($username, $server)
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
    public function username($user_id, $username, UrlScript $url)
    {
        // global $username, $realName, $summary, $server, $key_public;
        $user = $this->database->table('users')->get($user_id);
        $img = $user->ref('images', 'avatar')->filename ?? 'avatar.webp';
        $params = ["username" => $username];
        $latte = $this->latteFactory->create();
        $userLink = $this->lg->link("Pub:user", $params);
        $user = array(
            "@context" => [
                "https://www.w3.org/ns/activitystreams",
                "https://w3id.org/security/v1"
            ],
            "id" => $userLink,
            "type" => "Person",
            "following" => $this->lg->link("Pub:following", $params),
            "followers" => $this->lg->link("Pub:followers", $params),
            "inbox" => $this->lg->link("Pub:inbox", $params),
            "outbox" => $this->lg->link("Pub:outbox", $params),
            "preferredUsername" => $username, //rawurldecode( $username ),
            "name" => $user->realname,
            "summaryMap" => [
                'en' => $latte->renderToString(__DIR__ . '/markdown.latte', ['markdown' => $user->bio]),
                'fi' => $latte->renderToString(__DIR__ . '/markdown.latte', ['markdown' => $user->bio_fi])
            ],
            "url" => $userLink,
            "manuallyApprovesFollowers" => false,
            "discoverable" => true,
            "published" => $user->keys_created_at,
            "icon" => [
                "type" => "Image",
                "mediaType" => "image/png",
                "url" => $url->resolve("{$this->settings->uploadDir}/{$img}"),
            ],
            "image" => [
                "type" => "Image",
                "mediaType" => "image/png",
                "url" => $url->resolve("/img/catlogo_wide.png"),
            ],
            "publicKey" => [
                "id" => "{$userLink}#main-key",
                "owner" => $userLink,
                "publicKeyPem" => $user->public_key,
            ]
        );
        // header( "Content-Type: application/activity+json" );
        return $user;
    }

    public function following($username)
    {
        // TODO: Maybe actually support following accounts in the future

        $following = array(
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $this->lg->link("Pub:following", ["username" => $username]),
            "type" => "Collection",
            "totalItems" => 0,
            "items" => []
        );
        //header( "Content-Type: application/activity+json" );
        return $following;
    }

    public function followers($user_id, $username)
    {

        $followers = $this->database->table('ap_followers')->where('followed_user_id', $user_id)->select("details_json->>'$.id' AS follower_id")->order('created_at DESC');
        $items = [];
        foreach ($followers as $follower) {
            $items[] = $follower->follower_id;
        }
        $followers = array(
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $this->lg->link("Pub:followers", ["username" => $username]),
            "type" => "Collection",
            "totalItems" => count($items),
            "items" => $items
        );
        // header( "Content-Type: application/activity+json" );
        return $followers;
    }

    public function guid()
    {
        return Random::generate();
    }

    public function inbox($user_id, $username, $input, $inbox_message, $verified)
    {
        // global $body, $server, $username, $key_private, $directories;

        // $inbox_message = Json::decode($input, true);
        // if (!$this->verifyHTTPSignature($input, $inbox_message)) {
        //     return false;
        // }

        // TODO: in the future don't bother saving unverified stuff. currently for debugging
        $values = [
            "recipient_id" => $user_id,
            "message_json" => $this->database::literal('jsonb(?)', $input),
            "verified" => $verified,
        ];
        $inbox_row = $this->database->table('ap_inbox')->insert($values);
        if (!$verified) {
            return false;
        }
        $inbox_type = $inbox_message["type"];
        //	This inbox only sends responses to follow requests.
        //	A remote server sends the inbox a follow request which is a JSON file saying who they are.
        //	The details of the remote user's server is saved to a file so that future messages can be delivered to the follower.
        //	An accept request is cryptographically signed and POST'd back to the remote server.
        $status = false;
        switch ($inbox_type) {
            case "Follow":

                //	Get the parameters
                $follower_id = $inbox_message["id"];    //	E.g. https://mastodon.social/(unique id)
                $follower_actor = $inbox_message["actor"]; //	E.g. https://mastodon.social/users/Edent

                //	Get the actor's profile as JSON
                $details_json = $this->getDataFromUrl($follower_actor, $user_id, $username);
                $follower_actor_details = Json::decode($details_json, true);

                //	Save the actor's data in `/data/followers/`
                // $follower_filename = urlencode($follower_actor);
                // file_put_contents($directories["followers"] . "/{$follower_filename}.json", json_encode($follower_actor_details));

                //	Get the new follower's Inbox
                $follower_inbox = $follower_actor_details["inbox"];

                //	Response Message ID
                //	This isn't used for anything important so could just be a random number
                $guid = $this->guid();

                $params = ["username" => $username];
                $userLink = $this->lg->link("Pub:user", $params);

                //	Create the Accept message to the new follower
                $message = [
                    "@context" => "https://www.w3.org/ns/activitystreams",
                    "id" => $this->lg->link("Pub:guid", ["username" => $guid]),
                    "type" => "Accept",
                    "actor" => $userLink,
                    "object" => [
                        "@context" => "https://www.w3.org/ns/activitystreams",
                        "id" => $follower_id,
                        "type" => $inbox_type,
                        "actor" => $follower_actor,
                        "object" => $userLink,
                    ]
                ];
                $message_json = Json::encode($message);
                // store the message in the database
                $values = [
                    "sender_id" => $user_id,
                    "id" => $guid,
                    "message_json" => $this->database::literal('jsonb(?)', $message_json),
                ];
                $outbox_row = $this->database->table('ap_outbox')->insert($values);
                //	The Accept is POSTed to the inbox on the server of the user who requested the follow
                $status = $this->sendMessageToSingle($follower_inbox, $message_json, $user_id, $username);
                $outbox_row->update(['http_status' => $status]);
                $follower_values = [
                    "followed_id" => $user_id,
                    "actor" => $follower_actor,
                    "details_json" => $this->database::literal('jsonb(?)', $details_json),
                    "follow_msg_id" => $inbox_row->rowid,
                    "accept_msg_id" => $outbox_row->rowid,
                ];
                $this->database->table('ap_followers')->insert($follower_values);
                $status = true;
                break;
            case "EchoRequest":
                $status = true;
                break;
            case "Undo":
                // case "Delete":
                // case "Update":
                // $id = $inbox_message["id"];
                $actor = $inbox_message["actor"];
                //	The thing being undone
                $object = $inbox_message["object"];

                //	Does the thing being undone have its own ID or Type?
                // $object_id = $object["id"] ?? $id;
                $object_type = $object["type"] ?? $inbox_type;
                // I don't really care if there is a message in the database about this
                // since the actor was verified (PubPresenter does that) and requested an unfollow,
                // that's enough. Delete is for Create activity, Update isn't an unfollow?
                if ("Follow" == $object_type && $inbox_type == "Undo") {
                    $this->database->table('ap_followers')->where([
                        "followed_id" => $user_id,
                        "actor" => $actor,
                    ])->delete();
                    Debugger::log("deleted follower {$actor} for user {$username}");
                    $status = true;
                }

                // I could do some updating or deleting, but also, I could just handle stuff when
                // reading. reading is free. writing is expensive... or so I'll claim while nodding wisely 🥸
                // or I could just run a cron job...

                // $rows = $this->database->table('ap_inbox')->where([
                //     "message_json->>'$.actor'" => $actor,
                //     "recipient_id" => $user_id,
                // ])->whereOr([
                //     "message_json->>'$.id'" => $object_id,
                //     "message_json->>'$.object.id" => $object_id
                // ]);

                // foreach ($rows as $row) {
                //     ...
                // }
                break;
            default:
                break;
        }
        $inbox_row->update(['processed' => $status]);
        return $status;
    }

    public function createFromProduct(ActiveRow $product, $user_id, $username, UrlScript $url)
    {
        $latte = $this->latteFactory->create();
        $contentMap = [
            "en" => $latte->renderToString(__DIR__ . '/markdown.latte', ['markdown' => $product->description]),
            "fi" => $latte->renderToString(__DIR__ . '/markdown.latte', ['markdown' => $product->description_fi]),

        ];
        $summaryMap = [
            "en" => $product->brief,
            "fi" => $product->brief_fi,
        ];
        $nameMap = [
            "en" => $product->name,
            "fi" => $product->name_fi,
        ];
        $sourceMap = [
            "en" => [
                "content" => $product->description,
                "mediaType" => "text/markdown",
            ],
            "fi" => [
                "content" => $product->description_fi,
                "mediaType" => "text/markdown",
            ],
        ];
        $attachment = [];

        foreach ($product->related("product_gallery") as $product_image) {
            $image = $product_image->ref("images", "image");
            $filename = $image->filename;
            $filepath = "{$this->settings->uploadDir}/{$filename}";
            $url_string = $url->resolve($filepath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $mimetype = Image::typeToMimeType(Image::extensionToType($extension));
            $attachment[] = [
                "type" => "Image",
                "mediaType" => $mimetype,
                "url" => $url_string, 
                "nameMap" => [
                    "en" => $image->alt ?? $image->filename,
                    "fi" => $image->alt_fi ?? $image->filename,
                ]
            ];
        }

        $tags = [];
        $timestamp = date("c");
        $create_guid = $this->guid();
        $article_guid = $this->guid();
        $userLink = $this->lg->link("Pub:user", ["username" => $username]);

        $note = [
            "@context" => array(
                "https://www.w3.org/ns/activitystreams"
            ),
            "id" => $this->lg->link("Pub:guid", ["username" => $article_guid]),
            "type" => "Article",
            "published" => $timestamp,
            "attributedTo" => $userLink,
            "inReplyTo" => null,
            "nameMap" => $nameMap,
            "contentMap" => $contentMap,
            "summaryMap" => $summaryMap,
            "sourceMap" => $sourceMap,
            "to" => ["https://www.w3.org/ns/activitystreams#Public"],
            "tag" => $tags,
            "attachment" => $attachment
        ];
        $message = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id"       => $this->lg->link("Pub:guid", ["username" => $create_guid]),
            "type"     => "Create",
            "actor"    => $userLink,
            "to"       => [
                "https://www.w3.org/ns/activitystreams#Public"
            ],
            "cc"       => [
                $this->lg->link("Pub:followers", ["username" => $username]),
            ],
            "object"   => $note
        ];

        return $message;
    }

    public function outbox($username)
    {
        // global $server, $username, $directories;

        //	Get all posts
        // $posts = array_reverse( glob( $directories["posts"] . "/*.json") );
        //	Number of posts
        // $totalItems = count( $posts );
        //	Create an ordered list
        // $orderedItems = [];
        // foreach ( $posts as $post ) {
        // 	$postData = json_decode( file_get_contents( $post ), true );
        // 	$orderedItems[] = array(
        // 		"type"   => $postData["type"],
        // 		"actor"  => "https://{$server}/{$username}",
        // 		"object" => "https://{$server}/{$post}"
        // 	);
        // }

        //	Create User's outbox
        $outbox = array(
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $this->lg->link("Pub:outbox", ["username" => $username]),
            "type" => "OrderedCollection",
            "totalItems" => 0,
            "summary" => "All the user's posts",
            "orderedItems" => []
        );

        //	Render the page
        return $outbox;
    }

    public function wk_nodeinfo()
    {
        // global $server;

        $nodeinfo = array(
            "links" => array(
                array(
                    "rel" => "self",
                    "type" => "http://nodeinfo.diaspora.software/ns/schema/2.1",
                    "href" => $this->lg->link("Pub:nodeinfo"),
                )
            )
        );
        return $nodeinfo;
    }

    public function nodeinfo()
    {


        //	Get all posts
        // $posts =  glob( $directories["posts"] . "/*.json") ;
        //	Number of posts
        // $totalItems = count( $posts );
        $totalUsers = $this->database->table('users')->where("keys_created_at NOT", null)->count('*');
        $nodeinfo = array(
            "version" => "2.1",	//	Version of the schema, not the software
            "software" => array(
                "name" => "nettepuoti ActivityPub limited support",
                "version" => "3000", // in the not too distant future
                "repository" => "https://github.com/Gorhug/nettepuoti"
            ),
            "protocols" => array("activitypub"),
            "services" => array(
                "inbound" => array(),
                "outbound" => array()
            ),
            "openRegistrations" => false,
            "usage" => array(
                "users" => array(
                    "total" => $totalUsers,
                ),
                "localPosts" => 0
            ),
            "metadata" => array(
                "nodeName" => "nettepuoti",
                "nodeDescription" => "This is an extremely basic ActivityPub server.",
                "spdx" => "AGPL-3.0-or-later"
            )
        );
        return $nodeinfo;

    }


    public function getByGuid($guid)
    {
        $row = $this->database->table('ap_outbox')->select("json(message_json) AS m_json")->where('id', $guid)->fetch();
        return $row?->m_json;
    }

    public function getJsonFromUrl($url, $user_id, $username) {
        $json = $this->getDataFromUrl($url, $user_id, $username);
        return Json::decode($json, true);
    }
    public function getDataFromUrl($url, $user_id, $username)
    {
        //	Check this is a valid https address

        $parsed = new UrlImmutable($url);
        if ($parsed->getScheme() != "https") {
            throw new \Exception("Url scheme not https, {$url}");
        }
        //	Split the URL
        $url_host = $parsed->getHost();
        $url_path = $parsed->getPath();

        //	Generate signed headers for this request
        $headers = $this->generate_signed_headers(null, $url_host, $url_path, "GET", $user_id, $username);

        // Set cURL options
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USERAGENT);

        // Execute the cURL session
        $urlJSON = curl_exec($ch);

        $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        // Check for errors
        if (curl_errno($ch) || $status_code == 404) {
            // Handle cURL error
            // $error_message = curl_error( $ch ) . "\nUrl: {$url}\nHeaders: " . json_encode( $headers );
            $curl_error = curl_error($ch);
            $error_message = "Curl error: {$curl_error}, for Url: {$url}";
            throw new \Exception($error_message);
        }

        return $urlJSON;
    }

    public function generate_signed_headers($message_json, $host, $path, $method, $user_id, $username)
    {
        // global $server, $username, $key_private;

        //	Location of the Public Key
        $userLink = $this->lg->link("Pub:user", ["username" => $username]);
        $keyId = "{$userLink}#main-key";
        $key_private = $this->database->table('users')->get($user_id)->private_key;
        //	Get the Private Key
        $signer = openssl_get_privatekey($key_private);

        //	Timestamp this message was sent
        $date = date(DATE_RFC7231); // "D, d M Y H:i:s \G\M\T"

        //	There are subtly different signing requirements for POST and GET.
        if ("POST" == $method) {
            //	Encode the message object to JSON. <-- NOT HERE! (we could have parameter mismatch for the encoder)
            // $message_json = json_encode( $message );
            //	Generate signing variables
            $hash = hash("sha256", $message_json, true);
            $digest = base64_encode($hash);

            //	Sign the path, host, date, and digest
            $stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";

            //	The signing function returns the variable $signature
            //	https://www.php.net/manual/en/function.openssl-sign.php
            openssl_sign(
                $stringToSign,
                $signature,
                $signer,
                OPENSSL_ALGO_SHA256
            );
            //	Encode the signature
            $signature_b64 = base64_encode($signature);

            //	Full signature header
            $signature_header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . '"';

            //	Header for POST request
            $headers = array(
                "Host: {$host}",
                "Date: {$date}",
                "Digest: SHA-256={$digest}",
                "Signature: {$signature_header}",
                "Content-Type: application/activity+json",
                "Accept: application/activity+json",
            );
        } else if ("GET" == $method) {
            //	Sign the path, host, date - NO DIGEST because there's no message sent.
            $stringToSign = "(request-target): get $path\nhost: $host\ndate: $date";

            //	The signing function returns the variable $signature
            //	https://www.php.net/manual/en/function.openssl-sign.php
            openssl_sign(
                $stringToSign,
                $signature,
                $signer,
                OPENSSL_ALGO_SHA256
            );
            //	Encode the signature
            $signature_b64 = base64_encode($signature);

            //	Full signature header
            $signature_header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date",signature="' . $signature_b64 . '"';

            //	Header for GET request
            $headers = array(
                "Host: {$host}",
                "Date: {$date}",
                "Signature: {$signature_header}",
                "Accept: application/activity+json, application/json",
            );
        }

        return $headers;
    }

    public function sendMessageToSingle($inbox, $message_json, $user_id, $username)
    {
        // global $directories;
        $parsed = new UrlImmutable($inbox);
        $inbox_host = $parsed->getHost();
        $inbox_path = $parsed->getPath();

        //	Generate the signed headers
        $headers = $this->generate_signed_headers($message_json, $inbox_host, $inbox_path, "POST", $user_id, $username);

        //	POST the message and header to the requester's inbox
        $ch = curl_init($inbox);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USERAGENT);
        curl_exec($ch);

        //	Check for errors
        if (curl_errno($ch)) {
            // $error_message = curl_error( $ch ) . "\ninbox: {$inbox}\nmessage: " . json_encode($message);
            // file_put_contents( $directories["logs"] . "/{$timestamp}.Error.txt", $error_message );
            $curl_error = curl_error($ch);
            $error_message = "Curl error: {$curl_error}, for inbox: {$inbox}";
            throw new \Exception($error_message);
        }
        $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        Debugger::log("Send message to {$inbox} status_code: {$status_code}");
        // if ($status_code != 200) {
        //     $error_message = "Send message to {$inbox} status_code: {$status_code}";
        //     throw new \Exception($error_message);
        // }
        return $status_code;
    }


}