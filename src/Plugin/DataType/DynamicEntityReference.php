<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\DataType\DynamicEntityReference.
 */

namespace Drupal\dynamic_entity_reference\Plugin\DataType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;

/**
 * Defines a 'dynamic_entity_reference' data type.
 *
 * This serves as 'dynamic_entity' property of entity reference field items and
 * gets its value set from the parent.
 *
 * @code
 * $definition = \Drupal\Core\Entity\EntityDefinition::create($entity_type)
 *   ->addConstraint('Bundle', $bundle);
 * \Drupal\Core\TypedData\DataReferenceDefinition::create('dynamic_entity')
 *   ->setTargetDefinition($definition);
 * @endcode
 *
 * @DataType(
 *   id = "dynamic_entity_reference",
 *   label = @Translation("Dynamic entity reference"),
 *   definition_class = "\Drupal\dynamic_reference\DataDynamicReferenceDefinition"
 * )
 */
class DynamicEntityReference extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    if (!isset($this->target) && isset($this->id)) {
      // If we have a valid reference, return the entity object which is typed
      // data itself.
      $target_type = $this->getTargetDefinition()->getEntityTypeId() ?: $this->parent->getValue()['target_type'];
      $entity = entity_load($target_type, $this->id);
      $this->target = isset($entity) ? $entity->getTypedData() : NULL;
    }
    // Keep the entity-type in sync.
    if ($this->target) {
      $this->getTargetDefinition()->setEntityTypeId($this->target->getValue()
        ->getEntityTypeId());
    }
    return $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    unset($this->target);
    unset($this->id);

    // Both the entity ID and the entity object may be passed as value. The
    // reference may also be unset by passing NULL as value.
    if (!isset($value)) {
      $this->target = NULL;
    }
    elseif ($value instanceof EntityInterface) {
      $this->target = $value->getTypedData();
      $this->getTargetDefinition()->setEntityTypeId($value->getEntityTypeId());
    }
    elseif (!is_scalar($value) || $this->getTargetDefinition()->getEntityTypeId() === NULL) {
      throw new \InvalidArgumentException('Value is not a valid entity.');
    }
    else {
      $this->id = $value;
    }
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
