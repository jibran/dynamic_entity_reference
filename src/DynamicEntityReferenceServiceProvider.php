<?php

namespace Drupal\dynamic_entity_reference;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Entity\Query\Sql\QueryFactory as BaseQueryFactory;
use Drupal\Core\Entity\Query\Sql\pgsql\QueryFactory as BasePgsqlQueryFactory;
use Drupal\dynamic_entity_reference\Normalizer\DynamicEntityReferenceItemNormalizer;
use Drupal\dynamic_entity_reference\Query\PgsqlQueryFactory;
use Drupal\dynamic_entity_reference\Query\QueryFactory;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Service Provider for Dynamic Entity Reference.
 */
class DynamicEntityReferenceServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['hal'])) {
      // Hal module is enabled, add our new normalizer for dynamic entity
      // reference items.
      // To avoid problems the arguments to
      // \Drupal\hal\Normalizer\EntityReferenceItemNormalizer change, re-use
      // the same constructor arguments and set the additional dependency
      // with a setter method.
      $parent_definition = $container->getDefinition('serializer.normalizer.entity_reference_item.hal');
      $service_definition = new Definition(DynamicEntityReferenceItemNormalizer::class, $parent_definition->getArguments());

      // The priority must be higher than that of
      // serializer.normalizer.entity_reference_item.hal in
      // hal.services.yml.
      $service_definition->addTag('normalizer', ['priority' => $parent_definition->getTags()['normalizer'][0]['priority'] + 1]);
      $container->setDefinition('serializer.normalizer.entity.dynamic_entity_reference_item.hal', $service_definition);

    }
    $map = [
      'entity.query.sql' => [
        'old' => BaseQueryFactory::class,
        'new' => QueryFactory::class,
      ],
      'pgsql.entity.query.sql' => [
        'old' => BasePgsqlQueryFactory::class,
        'new' => PgsqlQueryFactory::class,
      ],
    ];
    foreach ($map as $service_id => $data) {
      if ($container->hasDefinition($service_id)) {
        $service_definition = $container->getDefinition($service_id);
        if ($service_definition->getClass() == $data['old']) {
          $service_definition->setClass($data['new']);
          $container->setDefinition($service_id, $service_definition);
        }
      }
    }
  }

}
