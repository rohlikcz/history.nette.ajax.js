<?php

namespace VojtechDobes\NetteAjax;

use Nette;
use Nette\DI;

/**
 * Provides support for History API
 */
class HistoryExtension extends DI\CompilerExtension
{

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('handler'))
			->setClass(HistoryRequestHandler::class);
	}



	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$builder->getDefinition($builder->getByType(Nette\Application\Application::class))
			->setFactory(Application::class)
			->addSetup('$service->onStartup[] = [?, ?]', array($this->prefix('@handler'), 'onStartup'))
			->addSetup('$service->onRequest[] = [?, ?]', array($this->prefix('@handler'), 'onRequest'))
			->addSetup('$service->onResponse[] = [?, ?]', array($this->prefix('@handler'), 'onResponse'));
	}

}
