<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldType;

use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\dynamic_entity_reference\DataDynamicReferenceDefinition;
use Drupal\entity_reference\ConfigurableEntityReferenceItem;

/**
 * Defines the 'dynamic_entity_reference' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - exclude_entity_types: Allow user to include or exclude entity_types.
 * - entity_type_ids: The entity type ids that can or cannot be referenced.
 *
 * @FieldType(
 *   id = "dynamic_entity_reference",
 *   label = @Translation("Dynamic entity reference"),
 *   description = @Translation("An entity field containing a dynamic entity reference."),
 *   no_ui = FALSE,
 *   default_widget = "dynamic_entity_reference_default",
 *   default_formatter = "dynamic_entity_reference_label",
 *   constraints = {"ValidReference" = {}}
 * )
 */
class DynamicEntityReferenceItem extends ConfigurableEntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'exclude_entity_types' => TRUE,
      'entity_type_ids' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $default_settings = array();
    $labels = \Drupal::entityManager()->getEntityTypeLabels(TRUE);
    $options = $labels['Content'];
    // Field storage settings are not accessible here so we are assuming that
    // all the entity types are referenceable by default.
    // See https://www.drupal.org/node/2346273#comment-9385179 for more details.
    foreach (array_keys($options) as $entity_type_id) {
      $default_settings[$entity_type_id] = array(
        'handler' => 'default',
        'handler_settings' => array(),
      );
    }
    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_id'] = DataDefinition::create('string')
      ->setLabel(t('Entity ID'));
    $properties['target_type'] = DataDefinition::create('string')
      ->setLabel(t('Target Entity Type'));
    $properties['entity'] = DataDynamicReferenceDefinition::create('entity')
      ->setLabel(t('Entity'))
      ->setDescription(t('The referenced entity'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE);

    if (isset($settings['target_bundle'])) {
      // @todo Add new NotBundle validator
      // $properties['entity']->getTargetDefinition()->addConstraint('Bundle', $settings['target_bundle']);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'target_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = array(
      'target_id' => array(
        'description' => 'The ID of the target entity.',
        'type' => 'varchar',
        'length' => '255',
      ),
      'target_type' => array(
        'description' => 'The Entity Type ID of the target entity.',
        'type' => 'varchar',
        'length' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      ),
    );

    $schema = array(
      'columns' => $columns,
      'indexes' => array(
        'target_id' => array('target_id'),
      ),
    );

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    if ($property_name == 'target_type') {
      $this->get('entity')->getDataDefinition()->getTargetDefinition()->setEntityTypeId($this->get('target_type')->getValue());
    }
    // Make sure that the target type and the target property stay in sync.
    elseif ($property_name == 'entity') {
      $this->writePropertyValue('target_type', $this->get('entity')->getValue()->getEntityTypeId());
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    // @todo inject this.
    $labels = \Drupal::entityManager()->getEntityTypeLabels(TRUE);

    $element['exclude_entity_types'] = array(
      '#type' => 'checkbox',
      '#title' => t('Exclude the selected items'),
      '#default_value' => $this->getSetting('exclude_entity_types'),
      '#disabled' => $has_data,
    );

    $element['entity_type_ids'] = array(
      '#type' => 'select',
      '#title' => t('Select items'),
      '#options' => $labels['Content'],
      '#default_value' => $this->getSetting('entity_type_ids'),
      '#disabled' => $has_data,
      '#multiple' => TRUE,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {

    $settings_form = array();
    $field = $form_state->get('field');
    $settings = $this->getSettings();
    $entity_type_ids = static::getAllEntityTypeIds($settings);
    foreach (array_keys($entity_type_ids) as $entity_type_id) {
      // We put the dummy value here so selection plugins can work.
      // @todo Remove these once https://www.drupal.org/node/1959806
      //   and https://www.drupal.org/node/2107243 are fixed.
      $field->settings['target_type'] = $entity_type_id;
      $field->settings['handler'] = $settings[$entity_type_id]['handler'];
      $field->settings['handler_settings'] = $settings[$entity_type_id]['handler_settings'];
      $settings_form[$entity_type_id] = parent::fieldSettingsForm($form, $form_state);
      $settings_form[$entity_type_id]['handler']['#title'] = t('Reference type for @entity_type_id', array('@entity_type_id' => $entity_type_ids[$entity_type_id]));
    }
    return $settings_form;
  }

  /**
   * Form element validation handler; Stores the new values in the form state.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    if ($form_state->hasValue('field')) {
      $settings = $form_state->getValue(array('field', 'settings'));
      foreach (array_keys($settings) as $entity_type_id) {
        $form_state->unsetValue(array(
          'field',
          'settings',
          $entity_type_id,
          'handler_submit',
        ));
      }
      $form_state->get('field')->settings = $form_state->getValue(array('field', 'settings'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (empty($values['target_type']) && !empty($values['target_id'])) {
      throw new \InvalidArgumentException('No entity type was provided, value is not a valid entity.');
    }
    // Make sure that the reference object has the correct target type
    // set, so it can load the entity when requested.
    if (!empty($values['target_type'])) {
      $this->properties['entity']->getDataDefinition()->getTargetDefinition()->setEntityTypeId($values['target_type']);
    }
    if (isset($values) && !is_array($values)) {
      // If either a scalar or an object was passed as the value for the item,
      // assign it to the 'entity' property since that works for both cases.
      $this->set('entity', $values, $notify);
    }
    else {
      // We have to bypass the EntityReferenceItem::setValue() here because we
      // also want to invoke onChange for target_type.
      FieldItemBase::setValue($values, FALSE);
      // Support setting the field item with only one property, but make sure
      // values stay in sync if only property is passed.
      if (isset($values['target_id']) && !isset($values['entity'])) {
        $this->onChange('target_type', FALSE);
        $this->onChange('target_id', FALSE);
      }
      elseif (!isset($values['target_id']) && isset($values['entity'])) {
        $this->onChange('entity', FALSE);
      }
      elseif (isset($values['target_id']) && isset($values['entity'])) {
        // If both properties are passed, verify the passed values match. The
        // only exception we allow is when we have a new entity: in this case
        // its actual id and target_id will be different, due to the new entity
        // marker.
        if (($this->get('entity')->getTargetIdentifier() != $values['target_id']
            || $this->get('entity')->getTargetDefinition()->getEntityTypeId() != $values['target_type'])) {
          throw new \InvalidArgumentException('The target id, target type and entity passed to the dynamic entity reference item do not match.');
        }
      }
      // Notify the parent if necessary.
      if ($notify && $this->parent) {
        $this->parent->onChange($this->getName());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($include_computed = FALSE) {
    $values = parent::getValue($include_computed);
    if (!empty($values['target_type'])) {
      $this->properties['entity']->getDataDefinition()->getTargetDefinition()->setEntityTypeId($values['target_type']);
    }
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if ($this->hasNewEntity()) {
      $this->entity->save();
    }
    // Handle the case where an unsaved entity was directly set using the public
    // 'entity' property and then saved before this entity. In this case
    // ::hasNewEntity() will return FALSE but $this->target_id will still be
    // empty.
    if ((empty($this->target_id) || empty($this->target_type)) && $this->entity) {
      $this->set('target_id', $this->entity->id(), FALSE);
      $this->set('target_type', $this->entity->getEntityTypeId(), FALSE);
    }
  }

  /**
   * Helper function to get all the entity type ids that can be referenced.
   *
   * @param array $settings
   *   The settings of the field storage.
   *
   * @return string[]
   *   All the entity type ids that can be referenced.
   */
  public static function getAllEntityTypeIds($settings) {
    $labels = \Drupal::entityManager()->getEntityTypeLabels(TRUE);
    $options = $labels['Content'];

    if ($settings['exclude_entity_types']) {
      $entity_type_ids = array_diff_key($options, $settings['entity_type_ids'] ?: array());
    }
    else {
      $entity_type_ids = array_intersect_key($options, $settings['entity_type_ids'] ?: array());
    }
    return $entity_type_ids;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition) {
    $dependencies = [];

    if (is_array($field_definition->default_value) && count($field_definition->default_value)) {
      $target_entity_types = static::getAllEntityTypeIds($field_definition->getFieldStorageDefinition()->getSettings());
      foreach ($target_entity_types as $target_entity_type) {
        $key = $target_entity_type instanceof ConfigEntityType ? 'config' : 'content';
        foreach ($field_definition->default_value as $default_value) {
          if (is_array($default_value) && isset($default_value['target_uuid'])) {
            $entity = \Drupal::entityManager()->loadEntityByUuid($target_entity_type->id(), $default_value['target_uuid']);
            // If the entity does not exist do not create the dependency.
            // @see \Drupal\Core\Field\EntityReferenceFieldItemList::processDefaultValue()
            if ($entity) {
              $dependencies[$key][] = $entity->getConfigDependencyName();
            }
          }
        }
      }
    }
    return $dependencies;
  }

}
