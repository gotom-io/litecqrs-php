<?php

namespace LiteCQRS\Plugin\SymfonyBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class HandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('command_bus');
        $args       = $definition->getArguments();
        $args[1]    = $this->getProxyFactories('lite_cqrs.command_proxy_factory');
        $definition->setArguments($args);

        $definition = $container->findDefinition('litecqrs.event_message_bus');
        $args       = $definition->getArguments();
        $args[1]    = $this->getProxyFactories('lite_cqrs.event_proxy_factory');
        $definition->setArguments($args);

        $services = array();
        foreach ($container->findTaggedServiceIds('lite_cqrs.command_handler') as $id => $attributes) {
            $definition = $container->findDefinition($id);
            $class = $definition->getClass();

            $reflClass = new \ReflectionClass($class);
            foreach ($reflClass->getMethods() as $method) {
                if ($method->getNumberOfParameters() != 1) {
                    continue;
                }

                $commandParam = current($method->getParameters());

                if (!$commandParam->getClass() || !in_array('LiteCQRS\Command', class_implements($commandParam->getClass()->getName()))) {
                    continue;
                }

                $commandType = $commandParam->getClass()->getName();
                $services[$commandType] = $id;
            }
        }

        $commandBus = $container->findDefinition('command_bus');
        $commandBus->addMethodCall('registerServices', array($services));

        $services = array();
        foreach ($container->findTaggedServiceIds('lite_cqrs.event_handler') as $id => $attributes) {
            $definition = $container->findDefinition($id);
            $class = $definition->getClass();

            $reflClass = new \ReflectionClass($class);
            foreach ($reflClass->getMethods() as $method) {
                if ($method->getNumberOfParameters() != 1) {
                    continue;
                }

                $methodName = $method->getName();
                if (strpos($methodName, "on") !== 0) {
                    continue;
                }

                $eventName = strtolower(substr($methodName, 2));

                if (!isset($services[$eventName])) {
                    $services[$eventName] = array();
                }

                $services[$eventName][] = $id;
            }
        }

        $messageBus = $container->findDefinition('litecqrs.event_message_bus');
        $messageBus->addMethodCall('registerServices', array($services));
    }

    private function getProxyFactories($container, $tag)
    {
        $services = array();
        foreach ($container->findTaggedServiceIds($tag) as $id => $attributes) {
            if (!isset($attributes['priority'])) {
                $attributes['priority'] = 0;
            }
            if (!isset($services[$attributes['priority']])) {
                $services[$attributes['priority']] = array();
            }
            $services[$attributes['priority']][] = new Reference($id);
        }

        $flat = array();
        foreach (array_reverse($flag) as $services) {
            foreach ($services as $service) {
                $flat[] = $service;
            }
        }

        return $flat;
    }
}

