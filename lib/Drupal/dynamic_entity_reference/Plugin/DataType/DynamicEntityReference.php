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
   * The entity ID.
   *
   * @var integer|string
   */
  protected $id;

  /**
   * Returns the definition of the referenced entity.
   *
   * @return \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface
   *   The reference target's definition.
   */
  public function getTargetDefinition() {
    return $this->definition->getTargetDefinition();
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    if (!isset($this->target) && isset($this->id)) {
      // If we have a valid reference, return the entity object which is typed
      // data itself.
      $this->target = entity_load($this->parent->getValue()['target_type'], $this->id);
    }
    return $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetIdentifier() {
    if (isset($this->id)) {
      return $this->id;
    }
    elseif ($entity = $this->getValue()) {
      return $entity->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Entities are already typed data, so just return that.
    return $this->getTarget();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    unset($this->target);
    unset($this->id);

    // Both the entity ID and the entity object may be passed as value. The
    // reference may also be unset by passing NULL as value.
    if (!isset($value) || $value instanceof EntityInterface) {
      $this->target = $value;
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

  /**
   * {@inheritdoc}
   */
  public function getString() {
    if ($entity = $this->getValue()) {
      return $entity->label();
    }
    return '';
  }
}
