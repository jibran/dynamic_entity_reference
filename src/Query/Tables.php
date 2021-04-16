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
    // Parent method is overridden in order to choose the correct columns to
    // join on (string or int for the id and type for the entity type
    // specifier).
    $entity_type_id_key = $entity_type->getKey('id');
    $entity_type_id = $entity_type->id();
    $join_condition_type = '';

    if (($field_storage && $field_storage->getType() === 'dynamic_entity_reference') && ($entity_type_id_key !== FALSE)) {
      // DER basefield SQL column are named entity_name__target_id and
      // entity_name__target_type where as config field columns are named
      // field_name_target_id and field_name_target_type.
      $sql_column_type = str_replace('target_id', 'target_type', $sql_column);

      if (DynamicEntityReferenceItem::entityHasIntegerId($entity_type_id)) {
        $sql_column .= '_int';
      }

      // Prepare to modify the join with an entity type specifier. This can
      // prevent obscure cases where entities of different types (e.g. node vs.
      // taxonomy_term) with the same id are returned.
      $join_condition_type = " AND [$table].[$sql_column_type] = '$entity_type_id'";
    }

    $join_condition = "[%alias].[$entity_type_id_key] = [$table].[$sql_column]" . $join_condition_type;
    return $this->sqlQuery->leftJoin($entity_type->getBaseTable(), NULL, $join_condition);
  }

}
