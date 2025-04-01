<?php

namespace App\Presenters;

use App\Model\ActivityPubFacade;
use App\Model\UserFacade;
use DateTimeImmutable;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use Nette\Application\Responses\JsonResponse;

class PubPresenter extends Presenter
{
    public function __construct(
        private ActivityPubFacade $ap,
        private UserFacade $users
    ) {
        // $this->locale = 'en';
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
        $url = $http_request->getUrl();
        $server = $url->getHost();
        if ($server != $domain) {
            $this->error("Requested resource's domain does not match server domain.", 404);
        }
        if (!$this->users->getId($user)) {
            $this->error("User not found.", 404);
        }
        $this->sendJson($this->ap->webfinger($user, $domain));

    }

    public function sendActivityJson($data)
    {
        $this->sendResponse(new JsonResponse($data, 'application/activity+json'));
    }
    public function renderUser(string $username)
    {
        $http_request = $this->getHttpRequest();
        $username = ltrim($username, "@");
        $url = $http_request->getUrl();
        $server = $url->getHost();
        $user_id = $this->users->getId($username);
        if ($user_id) {
            $this->sendActivityJson($this->ap->username($user_id, $username, $server ));
        } else {
            $this->error("User not found.", 404);
        }
    }

    public function renderFollowing(string $username) {
        $username = ltrim($username, "@");
        $user_id = $this->users->getId($username);
        if ($user_id) {
            $this->sendActivityJson($this->ap->following($username));
        } else {
            $this->error("User not found.", 404);
        }
    }

    public function renderFollowers(string $username) {
        $username = ltrim($username, "@");
        $user_id = $this->users->getId($username);
        if ($user_id) {
            $this->sendActivityJson($this->ap->followers($user_id,$username));
        } else {
            $this->error("User not found.", 404);
        }
    }
    // public function renderNodeinfo()
    // {
    //     $this->sendJson($this->ap->nodeinfo());
    // }
}
