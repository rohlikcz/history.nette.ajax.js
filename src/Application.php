<?php

namespace VojtechDobes\NetteAjax;

use Nette;
use Nette\Application\ApplicationException;
use Nette\Application\IPresenterFactory;
use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette\Application\Responses;



/**
 * @method onStartup(Nette\Application\Application $sender)
 * @method onShutdown(Nette\Application\Application $sender, \Exception $e = NULL)
 * @method onRequest(Nette\Application\Application $sender, Request $request)
 * @method onPresenter(Nette\Application\Application $sender, Nette\Application\IPresenter $presenter)
 * @method onResponse(Nette\Application\Application $sender, Nette\Application\IResponse $response)
 * @method onError(Nette\Application\Application $sender, \Exception $e)
 */
class Application extends Nette\Application\Application
{

	/**
	 * @var Nette\Http\IRequest
	 */
	private $httpRequest;

	/**
	 * @var Nette\Http\IResponse
	 */
	private $httpResponse;

	/**
	 * @var IPresenterFactory
	 */
	private $presenterFactory;

	/**
	 * @var IRouter
	 */
	private $router;

	/**
	 * @var \ReflectionProperty
	 */
	private $presenterPropertyRefl;

	/**
	 * @var \ReflectionProperty
	 */
	private $requestsPropertyRefl;



	public function __construct(IPresenterFactory $presenterFactory, IRouter $router, Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse)
	{
		parent::__construct($presenterFactory, $router, $httpRequest, $httpResponse);
		$this->presenterFactory = $presenterFactory;
		$this->router = $router;
		$this->httpRequest = $httpRequest;
		$this->httpResponse = $httpResponse;
	}



	public function processRequest(Request $request)
	{
		if (count($this->requests) > self::$maxLoop) {
			throw new ApplicationException('Too many loops detected in application life cycle.');
		}

		$this->appendRequest($request);
		$this->onRequest($this, $request);

		$presenter = $this->presenterFactory->createPresenter($request->getPresenterName());
		$this->updatePresenter($presenter);

		$this->onPresenter($this, $presenter);
		$response = $presenter->run($request);

		if ($response instanceof Responses\ForwardResponse) {
			$this->processRequest($response->getRequest());

		} elseif ($response) {
			foreach ($this->onResponse as $handler) {
				$returned = call_user_func_array($handler, [$this, $response]);
				if ($returned instanceof Request) {
					$this->processRequest($returned);
					return;
				}
			}

			$response->send($this->httpRequest, $this->httpResponse);
		}
	}



	private function appendRequest(Request $request)
	{
		if ($this->requestsPropertyRefl === NULL) {
			$this->requestsPropertyRefl = new \ReflectionProperty(Nette\Application\Application::class, 'requests');
			$this->requestsPropertyRefl->setAccessible(TRUE);
		}

		$requests = $this->requestsPropertyRefl->getValue($this);
		$requests[] = $request;
		$this->requestsPropertyRefl->setValue($this, $requests);
	}



	private function updatePresenter(Nette\Application\IPresenter $presenter = NULL)
	{
		if ($this->presenterPropertyRefl === NULL) {
			$this->presenterPropertyRefl = new \ReflectionProperty(Nette\Application\Application::class, 'presenter');
			$this->presenterPropertyRefl->setAccessible(TRUE);
		}

		$this->presenterPropertyRefl->setValue($this, $presenter);
	}

}
