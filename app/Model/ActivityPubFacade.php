<?php
/*
This file contains some code from https://gitlab.com/edent/activitypub-single-php-file, Copyright Terence Eden.

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
namespace App\Model;

use DateTimeImmutable;
use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Caching\Cache;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
use Nette\Http\UrlImmutable;
use Nette\Http\UrlScript;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\Json;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

final class ActivityPubFacade
{
    private const USERAGENT = "salakapakka/0.1";
    private Cache $cache;
    // public \Closure $getCachedJson;
    public function __construct(
        private \Nette\Database\Explorer $database,
        private \App\Settings $settings,
        private LinkGenerator $lg,
        private LatteFactory $latteFactory,
        \Nette\Caching\Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'activitypub');
        // $this->getCachedJson = $this->cache->wrap([$this, 'getJsonFromUrl'], [Cache::Expire => '20 minutes']);
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
        $webfinger = [
            "subject" => "acct:{$username}@{$server}",
            "links" => [
                [
                    "rel" => "self",
                    "type" => "application/activity+json",
                    "href" => $this->lg->link("Pub:user", ["username" => $username]),
                ],
                [
                    "rel" => "http://webfinger.net/rel/profile-page",
                    "type" => "text/html",
                    "href" => $this->lg->link("Kapakka:profile", ["username" => $username]),
                ],
            ]
        ];
        // header( "Content-Type: application/json" );
        return $webfinger;
    }
    public function username($user_id, $username, UrlScript $url)
    {
        // global $username, $realName, $summary, $server, $key_public;
        $user = $this->database->table('users')->get($user_id);
        $img = $user->ref('images', 'avatar')->filename ?? 'avatar.webp';
        $extension = pathinfo($img, PATHINFO_EXTENSION);
        $mimetype = Image::typeToMimeType(Image::extensionToType($extension));
        $params = ["username" => $username];
        $latte = $this->latteFactory->create();
        $userLink = $this->lg->link("Pub:user", $params);
        $enSummary = $latte->renderToString(__DIR__ . '/markdown.latte', ['markdown' => $user->bio]);
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
            "summary" => $enSummary,
            "summaryMap" => [
                'en' => $enSummary,
                'fi' => $latte->renderToString(__DIR__ . '/markdown.latte', ['markdown' => $user->bio_fi])
            ],
            "url" => $this->lg->link("Kapakka:profile", $params),
            "manuallyApprovesFollowers" => false,
            "discoverable" => true,
            "published" => $user->keys_created_at,

            "icon" => [
                "type" => "Image",
                "mediaType" => $mimetype,
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

        // $followers = $this->database->table('ap_followers')->where('followed_id', $user_id)->select("details_json->>'$.id' AS follower_id")->order('created_at DESC');
        $followers = $this->database->query("SELECT details_json->>'$.id' AS follower_id FROM ap_followers WHERE followed_id = ? ORDER BY created_at DESC", $user_id);
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
            case "Delete":
                // TODO: if it's a follower update store new data
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
                // that's enough. 
                if ("Follow" == $object_type) {
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
                "name" => $image->alt ?? $image->filename,
                "nameMap" => [
                    "en" => $image->alt ?? $image->filename,
                    "fi" => $image->alt_fi ?? $image->filename,
                ]
            ];
        }
        $hashtags = [];
        $hashtag_pattern = '/(?:^|\s)\#(\w+)/u';	//	Beginning of string, or whitespace, followed by #
        preg_match_all($hashtag_pattern, "{$product->description} {$product->description_fi}", $hashtag_matches);
        foreach ($hashtag_matches[1] as $match) {
            $hashtags[Strings::lower($match)] = $match;
        }

        //	Construct the tag value for the note object
        $tags = [];
        foreach ($hashtags as $key => $value) {
            $tags[] = array(
                "type" => "Hashtag",
                "name" => "#{$value}",
            );
        }

        $timestamp = date("c");
        $create_guid = $this->guid();
        $article_guid = $this->guid();
        $userLink = $this->lg->link("Pub:user", ["username" => $username]);
        $article_full_guid = $this->lg->link("Pub:guid", ["username" => $article_guid]);
        $note = [
            "@context" => array(
                "https://www.w3.org/ns/activitystreams"
            ),
            "id" => $article_full_guid,
            "type" => "Article",
            "published" => $timestamp,
            "attributedTo" => $userLink,
            "inReplyTo" => null,
            "name" => $nameMap["en"],
            "nameMap" => $nameMap,
            "content" => $contentMap["en"],
            "contentMap" => $contentMap,
            "summary" => $summaryMap["en"],
            "summaryMap" => $summaryMap,
            "source" => $sourceMap["en"],
            "sourceMap" => $sourceMap,
            "to" => ["https://www.w3.org/ns/activitystreams#Public"],
            "tag" => $tags,
            "attachment" => $attachment,
            "replies" => $this->lg->link("Pub:replies", ["username" => $article_guid]),
        ];
        $message = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $this->lg->link("Pub:guid", ["username" => $create_guid]),
            "type" => "Create",
            "actor" => $userLink,
            "to" => [
                "https://www.w3.org/ns/activitystreams#Public"
            ],
            "cc" => [
                $this->lg->link("Pub:followers", ["username" => $username]),
            ],
            "object" => $note
        ];
        $message_json = Json::encode($message);
        // store the message in the database
        $values = [
            "sender_id" => $user_id,
            "message_json" => $this->database::literal('jsonb(?)', $message_json),
        ];
        $outbox_row = $this->database->table('ap_outbox')->insert($values);
        $status = $this->sendMessageToFollowers($message_json, $user_id, $username, $outbox_row->rowid);
        $product->update(['ap_guid' => $article_full_guid, 'published_at' => new DateTimeImmutable($timestamp)]);
        $outbox_row->update(['http_status' => $status]);
        return $status;
    }

    public function replies($replies_guid)
    {
        $row = $this->database->fetch("SELECT message_json->>'$.actor' AS actor, sender_id FROM ap_outbox WHERE message_json->>'$.object.replies' = ?", $replies_guid);
        if (!$row) {
            return null;
        }
        $message = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $replies_guid,
            "type" => "OrderedCollection",
            "attributedTo" => $row->actor,
            "to" => [
                $this->lg->link("Pub:followers", ["username" => $this->database->table("users")->get($row->sender_id)->username]),
            ],
            "totalItems" => 0,
            "items" => [],
        ];
        return $message;
    }
    public function sendMessageToFollowers($message_json, $user_id, $username, $outbox_rowid)
    {
        // global $directories;
        //	Read existing followers

        // $followers = $this->database->table('ap_followers')->where("followed_id", $user_id)->select("details_json->>'$.endpoints.sharedInbox' AS shared_inbox, details_json->>'$.inbox' AS inbox");
        $followers = $this->database->query("SELECT details_json->>'$.endpoints.sharedInbox' AS shared_inbox, details_json->>'$.inbox' AS inbox FROM ap_followers WHERE followed_id = ?", $user_id);

        //	Get all the inboxes
        $inboxes = [];
        foreach ($followers as $follower) {
            //	Some servers have "Shared inboxes"
            //	If you have lots of followers on a single server, you only need to send the message once.
            $inbox = $follower->shared_inbox ?? $follower->inbox;
            $inboxes[$inbox] = true;
        }
        if (count($inboxes) == 0) {
            Debugger::log("No followers found for user {$username}");
            return false;
        }
        //	Prepare to use the multiple cURL handle
        //	This makes it more efficient to send many simultaneous messages
        $mh = curl_multi_init();

        //	Loop through all the inboxes of the followers
        //	Each server needs its own cURL handle
        //	Each POST to an inbox needs to be signed separately
        foreach ($inboxes as $inbox => $value) {

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

            //	Add the handle to the multi-handle
            curl_multi_add_handle($mh, $ch);
        }

        //	Execute the multi-handle
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status == CURLM_OK);

        $deliveries = [];
        do {
            $msg = curl_multi_info_read($mh);
            if ($msg) {
                $info = curl_getinfo($msg['handle']);
                $http_code = $info['http_code'];
                $url = $info['url'];
                if ($msg['result'] != CURLE_OK) {
                    Debugger::log("Curl error: " . curl_error($msg['handle']));
                    $status = 0 - $msg['result'];
                } else {
                    // Debugger::log("Curl response for {$url}:  {$http_code}", ILogger::DEBUG);
                    $status = $http_code;
                }
                $deliveries[] = [
                    "inbox_url" => $url,
                    "status" => $status,
                    "message_rowid" => $outbox_rowid
                ];
            }
        } while ($msg);
        $this->database->table('ap_delivery')->insert($deliveries);

        return true;
    }

    public function outbox($user_id, $username)
    {
        $posts = $this->database->query("
            SELECT message_json->>'$.object.id' AS object_id, message_json->>'$.id' AS id, 
            message_json->>'$.type' AS type, message_json->>'$.object.type' AS object_type,
            message_json->>'$.actor' AS actor 
            FROM ap_outbox 
            WHERE sender_id = ? AND message_json->>'$.type' IN ? ORDER BY created_at DESC",
            $user_id,
            ['Create', 'Like', 'Announce']
        );
        $items = [];
        foreach ($posts as $post) {
            $id = $post->object_id ?? $post->id;
            $type = $post->object_type ?? $post->type;
            $items[] = array(
                "type" => $type,
                "actor" => $post->actor,
                "object" => $id,
            );
        }
        //	Create User's outbox
        $outbox = array(
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $this->lg->link("Pub:outbox", ["username" => $username]),
            "type" => "OrderedCollection",
            "totalItems" => count($items),
            "summaryMap" => ["en" => "All the user's posts", "fi" => "Käyttäjän julkaisut"],
            "orderedItems" => $items,
        );

        //	Render the page
        return $outbox;
    }

    public function messages($user_id) {
        $messages = $this->database->query("
            SELECT products.id AS id, products.name AS name, products.name_fi as name_fi
            FROM ap_outbox INNER JOIN products
            ON products.ap_guid = message_json->>'$.object.id'
            WHERE sender_id = ? AND message_json->>'$.type' IN ? ORDER BY products.published_at DESC",
            $user_id,
            ['Create', ]//'Like', 'Announce']
        );
  
        return $messages;
    
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
        // $totalPosts = $this->database->table('ap_outbox')->where("message_json->>'$.type'", "Create")->count('*');
        $totalPosts = $this->database->query("SELECT COUNT(*) AS total FROM ap_outbox WHERE message_json->>'$.type' = 'Create'")->fetchField();
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
                "localPosts" => $totalPosts,
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
        $row = $this->database->fetch("SELECT message_json->>'$.id' AS id, message_json->>'$.object.id' AS object_id, 
            message_json->'$' AS full_json, message_json->'$.object' as object_json 
            FROM ap_outbox WHERE ? IN (message_json->>'$.object.id', message_json->>'$.id')
            ORDER BY created_at DESC", $guid);
        if (!$row) {
            return null;
        }
        if ($row->object_id == $guid) {
            return $row->object_json;
        } else {
            return $row->full_json;
        }
    }

    public function getJsonFromUrl($url, $user_id, $username)
    {
        $fixedUrl = (new UrlImmutable($url))->withFragment('')->getAbsoluteUrl();
        return $this->cache->load("{$fixedUrl}#{$user_id}_{$username}", function (&$dependencies) use ($fixedUrl, $user_id, $username) {
            $dependencies[Cache::Expire] = '20 minutes';
            $json = $this->getDataFromUrl($fixedUrl, $user_id, $username);
            return Json::decode($json, true);
        });
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