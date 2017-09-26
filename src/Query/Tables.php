<?php

namespace Drupal\dynamic_entity_reference\Query;

use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\Query\Sql\Tables as BaseTables;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;

/**
 * Adds tables and fields to the SQL entity query.
 */
class Tables extends BaseTables {

  /**
   * {@inheritdoc}
   */
  protected function addNextBaseTable(EntityType $entity_type, $table, $sql_column, FieldStorageDefinitionInterface $field_storage = NULL) {
    // Parent method is overridden in order to choose the correct column to
    // join on (string or int).
    $entity_type_id_key = $entity_type->getKey('id');
    if (($field_storage && $field_storage->getType() === 'dynamic_entity_reference') && ($entity_type_id_key !== FALSE)) {
      if (DynamicEntityReferenceItem::entityHasIntegerId($entity_type->id())) {
        $sql_column .= '_int';
      }
    }
    return parent::addNextBaseTable($entity_type, $table, $sql_column, $field_storage);
  }

}
