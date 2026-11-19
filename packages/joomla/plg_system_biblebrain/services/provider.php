<?php
/**
 * Bible Brain Players for Joomla - service provider.
 *
 * @license GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Biblebrain\Plugin\System\Biblebrain\Extension\Biblebrain;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$plugin = new Biblebrain(
					$container->get(DispatcherInterface::class),
					(array) PluginHelper::getPlugin('system', 'biblebrain')
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
