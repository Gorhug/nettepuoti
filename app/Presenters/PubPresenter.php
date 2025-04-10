<?php

namespace App\Presenters;

use App\Model\ActivityPubFacade;
use App\Model\UserFacade;
use DateTimeImmutable;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\VoidResponse;
use Nette\Http\IResponse;
use Tracy\Debugger;

class PubPresenter extends Presenter
{
	public function __construct(
		private ActivityPubFacade $ap,
		private UserFacade $users
	) {
		// $this->locale = 'en';
	}

	public function beforeRender() {
		// setting this here so $this->error() processes as json
		$this->getHttpResponse()->setContentType('application/json', 'utf-8');
	}

	public function renderWebfinger()
	{
		$http_request = $this->getHttpRequest();
		$resource = $http_request->getQuery('resource');
		if (!$resource) {
			$this->error("No resource specified in request.", 400);
		}
		$arr = explode("@", ltrim($resource, "@"));
		if (count($arr) != 2) {
			$this->error("Invalid resource in request.", 400);
		} else {
			[$user, $domain] = $arr;
		}
		// remove acct: from user
		$user = substr($user, 5);
		$url = $http_request->getUrl();
		$server = $url->getHost();
		if ($server != $domain) {
			$this->error("Requested resource's domain does not match server domain.", 404);
		}
		if (!$this->users->getId($user)) {
			$this->error("User {$user} not found.", 404);
		}
		$this->sendJson($this->ap->webfinger($user, $domain));

	}

	public function sendActivityJson($data)
	{
		if (is_string($data)) {
			$response = $this->getHttpResponse();
			$response->setContentType('application/activity+json', 'utf-8');
			$this->sendResponse(new TextResponse($data));
		} else {
			$this->sendResponse(new JsonResponse($data, 'application/activity+json'));
		}
	}

	public function sendEmptyResponse($code) {
		$response = $this->getHttpResponse();
		$response->setCode($code);
		$this->sendResponse(new VoidResponse());
	}

	public function renderUser(string $username)
	{
		$http_request = $this->getHttpRequest();
		// $username = ltrim($username, "@");
		$url = $http_request->getUrl();
		$server = $url->getHost();
		$user_id = $this->users->getId($username);
		if ($user_id) {
			$this->sendActivityJson($this->ap->username($user_id, $username, $server));
		} else {
			$this->error("User not found.", 404);
		}
	}

	public function renderFollowing(string $username)
	{
		// $username = ltrim($username, "@");
		$user_id = $this->users->getId($username);
		if ($user_id) {
			$this->sendActivityJson($this->ap->following($username));
		} else {
			$this->error("User not found.", 404);
		}
	}

	public function renderFollowers(string $username)
	{
		// $username = ltrim($username, "@");
		$user_id = $this->users->getId($username);
		if ($user_id) {
			$this->sendActivityJson($this->ap->followers($user_id, $username));
		} else {
			$this->error("User not found.", 404);
		}
	}

	public function renderInbox(string $username)
	{
		// $username = ltrim($username, "@");
		$user_id = $this->users->getId($username);
		if (!$user_id) {
			$this->error("User not found.", 404);
		}
		$http_request = $this->getHttpRequest();
		$input = $http_request->getRawBody();
		$inbox_message = Json::decode($input, true);
		$headers = $http_request->getHeaders();
		$verified = $this->verifyHTTPSignature($input, $inbox_message, $headers, $user_id, $username);
		$status = $this->ap->inbox($user_id, $username, $input,$inbox_message, $verified);
		if (!$verified) {
			$this->error("Signature verification failed.", 401);
		}
		$code = $status ? IResponse::S204_NoContent : IResponse::S202_Accepted;
		$this->sendEmptyResponse($code);
	}

	public function renderNodeinfo() {
		$this->sendJson($this->ap->nodeinfo());
	}

	public function renderWk() {
		$this->sendJson($this->ap->wk_nodeinfo());
	}

	public function renderOutbox(string $username)
	{
		// $username = ltrim($username, "@");
		$user_id = $this->users->getId($username);
		if ($user_id) {
			$this->sendActivityJson($this->ap->outbox($username));
		} else {
			$this->error("User not found.", 404);
		}
	}

	public function renderGuid(string $username)
	{
		// $username is actually GUID in here
		$msg = $this->ap->getByGuid($username);
		if ($msg) {
			$this->sendActivityJson($msg);
		} else {
			$this->error("Message not found.", 404);
		}
	}
	public function verifyHTTPSignature($input, $body, $headers, $user_id, $username)
	{
		// global $input, $body, $server, $directories;

		$type = $body["type"];
		$id = $body["id"];
		$debug_info = "{$type}: {$id}";
		// $timestamp = ( new DateTimeImmutable() )->format( DATE_RFC3339_EXTENDED );

		//	Validate the timestamp
		//	7.2.4 of https://datatracker.ietf.org/doc/rfc9421/ 
		if (!isset($headers["date"])) {
			//	No date set
			Debugger::log("No date set in headers, {$debug_info}", Debugger::WARNING);
			return null;
		}
		$dateHeader = $headers["date"];
		$headerDatetime = DateTimeImmutable::createFromFormat('D, d M Y H:i:s T', $dateHeader);
		$currentDatetime = new DateTimeImmutable();

		//	First, check if the message was sent no more than ± 1 hour
		//	https://github.com/mastodon/mastodon/blob/82c2af0356ff888e9665b5b08fda58c7722be637/app/controllers/concerns/signature_verification.rb#L11
		// Calculate the time difference in seconds
		$timeDifference = abs($currentDatetime->getTimestamp() - $headerDatetime->getTimestamp());
		if ($timeDifference > 3600) {
			//	Write a log detailing the error
			//	Filename for the log
			Debugger::log("Header time doesn't match current time, {$debug_info}", Debugger::WARNING);
			return false;
		}

		//	Is there a significant difference between the Date header and the published timestamp?
		//	Two minutes chosen because Friendica is frequently more than a minute skewed
		$published = $body["published"] ?? 'now'; // message body might not have published time, should we even make this check?
		$publishedDatetime = new DateTimeImmutable($published);
		// Calculate the time difference in seconds
		$timeDifference = abs($publishedDatetime->getTimestamp() - $headerDatetime->getTimestamp());
		if ($timeDifference > 120) {
			//	Write a log detailing the error
			Debugger::log("Header time and body time differ, {$debug_info}", Debugger::WARNING);
			return false;
		}

		//	Validate the Digest
		//	It is the hash of the raw input string, in binary, encoded as base64.
		$digestString = $headers["digest"];

		//	Usually in the form `SHA-256=Ofv56Jm9rlowLR9zTkfeMGLUG1JYQZj0up3aRPZgT0c=`
		//	The Base64 encoding may have multiple `=` at the end. So split this at the first `=`
		$digestData = explode("=", $digestString, 2);
		$digestAlgorithm = $digestData[0];
		$digestHash = $digestData[1];

		//	There might be many different hashing algorithms
		//	TODO: Find a way to transform these automatically
		//	See https://github.com/superseriousbusiness/gotosocial/issues/1186#issuecomment-1976166659 and https://github.com/snarfed/bridgy-fed/issues/430 for hs2019
		$digestAlgorithm = strtolower($digestAlgorithm);
		if ("sha-256" == $digestAlgorithm || "hs2019" == $digestAlgorithm) {
			$digestAlgorithm = "sha256";
		} else if ("sha-512" == $digestAlgorithm) {
			$digestAlgorithm = "sha512";
		}

		//	Manually calculate the digest based on the data sent
		$digestCalculated = base64_encode(hash($digestAlgorithm, $input, true));

		//	Does our calculation match what was sent?
		if (!($digestCalculated == $digestHash)) {
			//	Write a log detailing the error
			Debugger::log("Digest match failure, {$debug_info}", Debugger::WARNING);
			return false;
		}

		//	Examine the signature
		$signatureHeader = $headers["signature"];

		// Extract key information from the Signature header
		$signatureParts = [];
		//	Converts 'a=b,c=d e f' into ["a"=>"b", "c"=>"d e f"]
		// word="text"
		preg_match_all('/(\w+)="([^"]+)"/', $signatureHeader, $matches);
		foreach ($matches[1] as $index => $key) {
			$signatureParts[$key] = $matches[2][$index];
		}

		//	Manually reconstruct the header string
		$signatureHeaders = explode(" ", $signatureParts["headers"]);
		$signatureString = "";
		foreach ($signatureHeaders as $signatureHeader) {
			if ("(request-target)" == $signatureHeader) {
				$method = strtolower($_SERVER["REQUEST_METHOD"]);
				$target = $_SERVER["REQUEST_URI"];
				$signatureString .= "(request-target): {$method} {$target}\n";
			} else if ("host" == $signatureHeader) {
				$host = strtolower($_SERVER["HTTP_HOST"]);
				$signatureString .= "host: {$host}\n";
			} else {
				$signatureString .= "{$signatureHeader}: " . $headers[$signatureHeader] . "\n";
			}
		}

		//	Remove trailing newline
		$signatureString = trim($signatureString);

		//	Get the Public Key
		//	The link to the key might be sent with the body, but is always sent in the Signature header.
		$publicKeyURL = $signatureParts["keyId"];

		//	This is usually in the form `https://example.com/user/username#main-key`
		//	This is to differentiate if the user has multiple keys
		//	TODO: Check the actual key
		try {
			$userDataJson = $this->ap->getDataFromUrl($publicKeyURL, $user_id, $username);
			$actorDataJson = $this->ap->getDataFromUrl($body["actor"], $user_id, $username);
			$userData = Json::decode($userDataJson, true);
			$actorData = Json::decode($actorDataJson, true);
		} catch (\Exception $e) {
			Debugger::log($e, Debugger::ERROR);
			return false;
		}
		$publicKey = $userData["publicKey"]["publicKeyPem"];

		//	Check that the actor's key is the same as the key used to sign the message
		//	Get the actor's public key

		$actorPublicKey = $actorData["publicKey"]["publicKeyPem"];

		if ($publicKey != $actorPublicKey) {
			Debugger::log("Signature keys don't match, {$debug_info}", Debugger::WARNING);
			return false;
		}

		//	Get the remaining parts
		$signature = base64_decode($signatureParts["signature"]);
		$algorithm = $signatureParts["algorithm"];

		//	There might be many different signing algorithms
		//	TODO: Find a way to transform these automatically
		//	See https://github.com/superseriousbusiness/gotosocial/issues/1186#issuecomment-1976166659 and https://github.com/snarfed/bridgy-fed/issues/430 for hs2019
		if ("hs2019" == $algorithm) {
			$algorithm = "sha256";
		}

		//	Finally! Calculate whether the signature is valid
		//	Returns 1 if verified, 0 if not, false or -1 if an error occurred
		$verified = openssl_verify(
			$signatureString,
			$signature,
			$publicKey,
			$algorithm
		);

		//	Convert to boolean
		if ($verified === 1) {
			$verified = true;
		} elseif ($verified === 0) {
			$verified = false;
		} else {
			$verified = null;
		}

		if ($verified) {
			Debugger::log("Signature verified, {$debug_info}", Debugger::INFO);
		} else {
			Debugger::log("Signature failure ({$verified}), {$debug_info}", Debugger::WARNING);
		}


		return $verified;
	}

	// public function renderNodeinfo()
	// {
	//     $this->sendJson($this->ap->nodeinfo());
	// }
}
