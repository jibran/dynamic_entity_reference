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
    $target_ids = array();
    $revision_ids = array();

    // Collect every possible entity attached to any of the entities.
    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        if (!empty($item->revision_id) && !empty($item->target_type)) {
          $revision_ids[$item->target_type][] = $item->revision_id;
        }
        elseif (!empty($item->target_id) && !empty($item->target_type)) {
          $target_ids[$item->target_type][] = $item->target_id;
        }
      }
    }

    $target_entities = array();

    if ($target_ids) {
      foreach ($target_ids as $target_type => $ids) {
        $target_entities[$target_type] = entity_load_multiple($target_type, $ids);
      }
    }

    if ($revision_ids) {
      // We need to load the revisions one by-one.
      foreach ($revision_ids as $target_type => $rev_ids) {
        foreach ($rev_ids as $revision_id) {
          $target_entity = entity_revision_load($target_type, $revision_id);
          // Use the revision ID in the key.
          $identifier = $target_entity->id() . ':' . $revision_id;
          $target_entities[$target_type][$identifier] = $target_entity;
        }
      }
    }

    // Iterate through the fieldable entities again to attach the loaded data.
    foreach ($entities_items as $items) {
      $rekey = FALSE;
      foreach ($items as $item) {
        // If we have a revision ID, the key uses it as well.
        $identifier = !empty($item->revision_id) ? $item->target_id . ':' . $item->revision_id : $item->target_id;
        if ($item->target_id !== 0) {
          if (!isset($item->target_type) || !isset($target_entities[$item->target_type][$identifier])) {
            // The entity no longer exists, so empty the item.
            $item->setValue(NULL);
            $rekey = TRUE;
            continue;
          }

          $item->entity = $target_entities[$item->target_type][$identifier];

          if (!$item->entity->access('view')) {
            continue;
          }
        }
        else {
          // This is an "auto_create" item, just leave the entity in place.
        }

        // Mark item as accessible.
        $item->access = TRUE;
      }

      // Rekey the items array if needed.
      if ($rekey) {
        $items->filterEmptyItems();
      }
    }
  }

}
