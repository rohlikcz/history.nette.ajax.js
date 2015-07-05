<?php

namespace VojtechDobes\NetteAjax;

use Kdyby\RequestStack\RequestStack;
use Nette;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http;



class HistoryRequestHandler
{

	const HISTORY_HEADER = 'X-History-Request';

	/**
	 * @var RequestStack
	 */
	private $httpRequest;

	/**
	 * @var bool
	 */
	private $forwardHasHappened = FALSE;

	/**
	 * @var string
	 */
	private $fragment;



	public function __construct(RequestStack $httpRequest)
	{
		$this->httpRequest = $httpRequest;
	}



	/**
	 * @return bool
	 */
	public function isAjaxHistory()
	{
		return $this->httpRequest->isAjax() && $this->httpRequest->getHeader(self::HISTORY_HEADER, FALSE);
	}



	public function onStartup(Nette\Application\Application $application)
	{
		$this->forwardHasHappened = FALSE;
	}



	public function onRequest(Nette\Application\Application $application, $request)
	{
		if ($this->isAjaxHistory() && count($application->getRequests()) > 1) {
			$this->forwardHasHappened = TRUE;
		}
	}



	public function onResponse(Nette\Application\Application $application, $response)
	{
		if (!$this->isAjaxHistory() || !$response instanceof JsonResponse || !($payload = $response->getPayload()) instanceof \stdClass) {
			return NULL; // ignore
		}

		if (!$this->forwardHasHappened && isset($payload->redirect)) {
			$this->fragment = ($fragmentPos = strpos($payload->redirect, '#')) !== FALSE ? substr($payload->redirect, $fragmentPos) : '';

			$url = new Http\UrlScript($payload->redirect);
			$url->setScriptPath($this->httpRequest->getUrl()->getScriptPath());
			$this->httpRequest->pushRequest(new Http\Request($url, NULL, [], [], $this->httpRequest->getCookies(), $this->httpRequest->getHeaders()));

			return $application->getRouter()->match($this->httpRequest->getCurrentRequest());

		} elseif ($this->forwardHasHappened && !isset($payload->redirect)) {
			/** @var Presenter $presenter */
			$presenter = $application->getPresenter();
			$payload->redirect = $presenter->link('this' . $this->fragment, $presenter->getParameters());
			$this->fragment = '';
		}

		return NULL;
	}

}
