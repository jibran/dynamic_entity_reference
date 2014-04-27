<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceWidget.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget;

use Drupal\entity_reference\Plugin\Field\FieldWidget\AutocompleteWidget;

/**
 * Plugin implementation of the 'dynamic_entity_reference autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "dynamic_entity_reference_default",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field."),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   }
 * )
 */
class DynamicEntityReferenceWidget extends AutocompleteWidget {

}
