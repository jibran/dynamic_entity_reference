<?php

/**
 * @file
 * Provides views data for the dynamic_entity_reference module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function dynamic_entity_reference_field_views_data(FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);
  $entity_manager = \Drupal::entityManager();
  $labels = $entity_manager->getEntityTypeLabels(TRUE);
  $options = array_keys($labels['Content']);
  $settings = $field_storage->getSettings();
  // Identify all the target entity type ids that can be referenced.
  if ($settings['exclude_entity_types']) {
    $target_entity_type_ids = array_diff($options, $settings['entity_type_ids'] ?: array());
  }
  else {
    $target_entity_type_ids = array_intersect($options, $settings['entity_type_ids'] ?: array());
  }
  foreach ($data as $table_name => $table_data) {
    // Add a relationship to all the target entity types.
    foreach ($target_entity_type_ids as $target_entity_type_id) {
      $target_entity_type = $entity_manager->getDefinition($target_entity_type_id);
      $entity_type_id = $field_storage->getTargetEntityTypeId();
      $entity_type = $entity_manager->getDefinition($entity_type_id);
      $target_base_table = $target_entity_type->getDataTable() ?: $target_entity_type->getBaseTable();
      $field_name = $field_storage->getName();

      // Provide a relationship for the entity type with the dynamic entity
      // reference field.
      $args = array(
        '@label' => $target_entity_type->getLabel(),
        '@field_name' => $field_name,
      );
      $data[$table_name][$target_entity_type_id . '__' . $field_name]['relationship'] = array(
        'title' => t('@label referenced from @field_name', $args),
        'label' => t('@field_name: @label', $args),
        'group' => $entity_type->getLabel(),
        'help' =>  t('Appears in: @bundles.', array('@bundles' => implode(', ', $field_storage->getBundles()))),
        'id' => 'standard',
        'base' => $target_base_table,
        'entity type' => $target_entity_type_id,
        'base field' => $target_entity_type->getKey('id'),
        'relationship field' => $field_name . '_target_id',
        // Entity reference field only has one target type whereas dynamic
        // entity reference field can have multiple target types that is why we
        // need extra join condition on target types.
        'extra' => array(
          array(
            'left_field' => $field_name . '_target_type',
            'value' => $target_entity_type_id,
          ),
        ),
      );

      // Provide a reverse relationship for the entity type that is referenced
      // by the field.
      $pseudo_field_name = 'reverse__' . $entity_type_id . '__' . $field_name;
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $entity_manager->getStorage($entity_type_id)->getTableMapping();
      $args['@entity'] = $entity_type->getLabel();
      $args['@label'] = $target_entity_type->getLowercaseLabel();
      $data[$target_base_table][$pseudo_field_name]['relationship'] = array(
        'title' => t('@entity using @field_name', $args),
        'label' => t('@field_name', array('@field_name' => $field_name)),
        'group' => $target_entity_type->getLabel(),
        'help' => t('Relate each @entity with a @field_name set to the @label.', $args),
        'id' => 'entity_reverse',
        'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
        'entity_type' => $entity_type_id,
        'base field' => $entity_type->getKey('id'),
        'field_name' => $field_name,
        'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
        'field field' => $field_name . '_target_id',
        // Entity reference field only has one target type whereas dynamic
        // entity reference field can have multiple target types that is why we
        // need extra join condition on target types.
        'join_extra' => array(
          array(
            'field' => $field_name . '_target_type',
            'value' => $target_entity_type_id,
          ),
          array(
            'field' => 'deleted',
            'value' => 0,
            'numeric' => TRUE,
          ),
        ),
      );
    }
  }

  return $data;
}
