<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Validation\Constraint\ValidDynamicReferenceConstraintValidator.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Validation\Constraint;

use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if referenced entities are valid.
 */
class ValidDynamicReferenceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /* @var \Drupal\Core\Field\FieldItemInterface $value */
    if (!isset($value)) {
      return;
    }
    $id = $value->get('target_id')->getValue();
    $type = $value->get('target_type')->getValue();
    $types = DynamicEntityReferenceItem::getAllEntityTypeIds($value->getFieldDefinition()->getSettings());
    $valid_type = !empty($type) && in_array($type, array_keys($types));
    // '0' or NULL are considered valid empty references.
    if (empty($id) && $valid_type) {
      return;
    }
    $referenced_entity = $value->get('entity')->getValue();
    if (!$valid_type || !$referenced_entity) {
      $this->context->addViolation($constraint->message, array('%type' => $type, '%id' => $id));
    }
  }
}
