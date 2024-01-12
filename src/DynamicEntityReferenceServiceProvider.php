<?php

namespace Drupal\dynamic_entity_reference;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Entity\Query\Sql\pgsql\QueryFactory as BasePgsqlQueryFactory;
use Drupal\Core\Entity\Query\Sql\QueryFactory as BaseQueryFactory;
use Drupal\dynamic_entity_reference\Query\PgsqlQueryFactory;
use Drupal\dynamic_entity_reference\Query\QueryFactory;

/**
 * Service Provider for Dynamic Entity Reference.
 */
class DynamicEntityReferenceServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
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
