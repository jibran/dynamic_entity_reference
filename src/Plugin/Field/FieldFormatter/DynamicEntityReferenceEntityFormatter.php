<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter\DynamicEntityReferenceEntityFormatter.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter;

use Drupal\entity_reference\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;

/**
 * Plugin implementation of the 'rendered entity' formatter.
 *
 * @FieldFormatter(
 *   id = "dynamic_entity_reference_entity_view",
 *   label = @Translation("Rendered entity"),
 *   description = @Translation("Display the referenced entities rendered by entity_view()."),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   }
 * )
 */
class DynamicEntityReferenceEntityFormatter extends EntityReferenceEntityFormatter {
  use DynamicEntityReferenceFormatterTrait;

}
