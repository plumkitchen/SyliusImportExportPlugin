<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\DependencyInjection\Compiler;

use FriendsOfSylius\SyliusImportExportPlugin\Importer\ImporterRegistry;
use Sylius\Bundle\UiBundle\Block\BlockEventListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterImporterPass implements CompilerPassInterface
{
    private $eventNames = [
        'taxonomy' => 'sonata.block.event.sylius.admin.taxon.create.after_content',
    ];

    private $templateNames = [
        'taxonomy' => '@FOSSyliusImportExportPlugin/Taxonomy/import.html.twig',
    ];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $serviceId = 'sylius.importers_registry';
        if ($container->has($serviceId) == false) {
            return;
        }

        $importersRegistry = $container->findDefinition($serviceId);

        foreach ($container->findTaggedServiceIds('sylius.importer') as $id => $attributes) {
            if (!isset($attributes[0]['type'])) {
                throw new \InvalidArgumentException('Tagged importer ' . $id . ' needs to have a type');
            }
            if (!isset($attributes[0]['format'])) {
                throw new \InvalidArgumentException('Tagged importer ' . $id . ' needs to have a format');
            }
            $type = $attributes[0]['type'];
            $format = $attributes[0]['format'];
            $name = ImporterRegistry::buildServiceName($type, $format);

            $importersRegistry->addMethodCall('register', [$name, new Reference($id)]);

            if ($container->getParameter('sylius.importer.web_ui')) {
                $this->registerImportFormBlockEvent($container, $type);
            }
        }
    }

    private function registerImportFormBlockEvent(ContainerBuilder $container, string $type): void
    {
        $eventHookName = ImporterRegistry::buildEventHookName($type) . '.import';

        if ($container->has($eventHookName)) {
            return;
        }

        $eventName = $this->buildEventName($type);
        $templateName = $this->buildTemplateName($type);
        $container
            ->register(
                $eventHookName,
                BlockEventListener::class
            )
            ->setAutowired(false)
            ->addArgument($templateName)
            ->addTag(
                'kernel.event_listener',
                [
                    'event' => $eventName,
                    'method' => 'onBlockEvent',
                ]
            )
        ;
    }

    private function buildEventName(string $type): string
    {
        if (isset($this->eventNames[$type])) {
            return $this->eventNames[$type];
        }

        $domain = 'sylius';
        // backward compatibility with the old configuration
        if (count(\explode('.', $type)) === 2) {
            [$domain, $type] = \explode('.', $type);
        }

        return \sprintf(
            'sonata.block.event.%s.admin.%s.index.after_content',
            $domain,
            $type
        );
    }

    private function buildTemplateName(string $type): string
    {
        if (isset($this->templateNames[$type])) {
            return $this->templateNames[$type];
        }

        return '@FOSSyliusImportExportPlugin/Crud/import.html.twig';
    }
}
