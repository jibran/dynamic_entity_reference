<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter\DynamicEntityReferenceFormatter.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter;
use Drupal\entity_reference\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;

/**
 * Plugin implementation of the 'dynamic entity reference label' formatter.
 *
 * @FieldFormatter(
 *   id = "dynamic_entity_reference_default",
 *   label = @Translation("Label"),
 *   description = @Translation("Display the label of the referenced entities."),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   }
 * )
 */
class DynamicEntityReferenceFormatter extends EntityReferenceLabelFormatter {

}
