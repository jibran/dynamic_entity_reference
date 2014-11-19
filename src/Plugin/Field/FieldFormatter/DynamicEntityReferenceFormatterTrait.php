<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter\DynamicEntityReferenceFormatterTrait.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter;

/**
 * Trait to override EntityReferenceFormatterBase::prepareView().
 */
trait DynamicEntityReferenceFormatterTrait {

  /**
   * Overrides EntityReferenceFormatterBase::prepareView().
   */
  public function prepareView(array $entities_items) {
    // Load the existing (non-autocreate) entities. For performance, we want to
    // use a single "multiple entity load" to load all the entities for the
    // multiple "entity reference item lists" that are being displayed. We thus
    // cannot use
    // \Drupal\Core\Field\EntityReferenceFieldItemList::referencedEntities().
    $ids = array();
    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        if ($item->target_id !== NULL) {
          $ids[$item->target_type][] = $item->target_id;
        }
      }
    }
    if ($ids) {
      foreach (array_keys($ids) as $target_type ) {
        $target_entities[$target_type] = \Drupal::entityManager()->getStorage($target_type)->loadMultiple($ids[$target_type]);
      }
    }

    // For each item, place the referenced entity where getEntitiesToView()
    // reads it.
    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        if (isset($target_entities[$item->target_type]) && isset($target_entities[$item->target_type][$item->target_id])) {
          $item->originalEntity = $target_entities[$item->target_type][$item->target_id];
        }
        elseif ($item->hasNewEntity()) {
          $item->originalEntity = $item->entity;
        }
      }
    }
  }

}
