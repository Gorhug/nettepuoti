<?php

namespace App\Presenters;

use App\Model\ActivityPubFacade;
use App\Model\UserFacade;
use DateTimeImmutable;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette\Utils\Json;
use Nette\Application\Responses\TextResponse;
class PubPresenter extends BasePresenter
{
    public function __construct(
        private ActivityPubFacade $ap,
        private UserFacade $users
    ) {
        $this->locale = 'en';
    }

    public function renderWebfinger()
    {
        $http_request = $this->getHttpRequest();
        $resource = $http_request->getQuery('resource');
        if (!$resource) {
            $this->error("No resource in query", 400);
        }
        [$user, $domain] = explode("@", ltrim($resource, "@"));
        $url = $http_request->getUrl();
        $server = $url->getHost();
        if ($server != $domain) {
            $this->error("Domain mismatch", 400);
        }
        if (!$this->users->getId($user)) {
            $this->error("User not found", 404);
        }
        $scheme = $url->getScheme();
        $this->sendJson($this->ap->webfinger($user, $domain, $scheme));

    }

    public function sendActivityJson($data)
    {
        $this->getHttpResponse()->setHeader('Content-Type', 'application/activity+json');
        $this->sendResponse(new TextResponse(JSON::encode($data)));
    }
    public function renderUser(string $user)
    {
        $http_request = $this->getHttpRequest();
        $user = ltrim($user, "@");
        $url = $http_request->getUrl();
        $server = $url->getHost();
        $this->sendActivityJson( $this->ap->username($user, $server, "not set", "none", "no key", (new DateTimeImmutable())->getTimestamp()));
    }

    // public function renderNodeinfo()
    // {
    //     $this->sendJson($this->ap->nodeinfo());
    // }
}
