<?php

namespace Tmtk\AwsLoggerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TmtkAwsLoggerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $loader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $definition = $containerBuilder->getDefinition('Tmtk\AwsLoggerBundle\EventSubscriber\EventSubscriber');

        if (isset($config['log_aws_events'])) {
            $definition->replaceArgument(2, $config['log_aws_events']);
        }

        if (isset($config['log_aws_errors'])) {
            $definition->replaceArgument(3, $config['log_aws_errors']);
        }

        $definition = $containerBuilder->getDefinition('Tmtk\AwsLoggerBundle\Service\Aws');

        if (isset($config['aws_access_key_id'])) {
            $definition->replaceArgument(0, $config['aws_access_key_id']);
        }

        if (isset($config['aws_secret_access_key'])) {
            $definition->replaceArgument(1, $config['aws_secret_access_key']);
        }

        /*$this->addAnnotatedClassesToCompile([
            // you can define the fully qualified class names...
            // 'App\\Controller\\DefaultController',
        ]);*/
    }
}