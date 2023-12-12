<?php

namespace Tmtk\AwsLoggerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('tmtk_aws_logger');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('log_aws_events')->end()
                ->booleanNode('log_aws_errors')->end()
                ->scalarNode('aws_access_key_id')->end()
                ->scalarNode('aws_secret_access_key')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}