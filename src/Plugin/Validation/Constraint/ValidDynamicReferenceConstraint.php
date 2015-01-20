<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Validation\Constraint\ValidDynamicReferenceConstraint.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Dynamic Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 *
 * @Plugin(
 *   id = "ValidDynamicReference",
 *   label = @Translation("Dynamic Entity Reference valid reference", context = "Validation")
 * )
 */
class ValidDynamicReferenceConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The referenced entity (%type: %id) does not exist.';

}
