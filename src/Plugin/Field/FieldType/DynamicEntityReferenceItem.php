<?php
/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\dynamic_entity_reference\DataDynamicReferenceDefinition;
use Drupal\entity_reference\ConfigurableEntityReferenceItem;

/**
 * Defines the 'dynamic_entity_reference' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - excluded_entity_type_ids: The entity type ids that cannot be referenced.
 * - excluded_bundle_ids: The bundle ids that cannot be referenced.
 *   Specified as {entity_type}:{bundle_id}
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
  public static function defaultSettings() {
    return array(
      'excluded_entity_type_ids' => array(),
      'excluded_bundle_ids' => array(),
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultInstanceSettings() {
    return array(
      'handler' => 'default',
    ) + parent::defaultInstanceSettings();
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
  public function onChange($property_name) {
    // Make sure that the target ID and type and the target property stay in
    // sync.
    // @todo something with target_type
    if ($property_name == 'target_id') {
      $this->properties['entity']->setValue($this->target_id, FALSE);
    }
    elseif ($property_name == 'entity') {
      $this->set('target_id', $this->properties['entity']->getTargetIdentifier(), FALSE);
    }
    parent::onChange($property_name);
  }

  /**
   * {@inheritdoc}
   * @todo update
   */
  public function settingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    // @todo inject this.
    $labels = \Drupal::entityManager()->getEntityTypeLabels(TRUE);

    $element['excluded_entity_type_ids'] = array(
      '#type' => 'select',
      '#title' => t('Items to exclude'),
      '#options' => $labels['Content'],
      '#default_value' => $this->getSetting('excluded_entity_type_ids'),
      '#disabled' => $has_data,
      '#multiple' => TRUE,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function instanceSettingsForm(array $form, FormStateInterface $form_state) {
    return array();
  }

  /**
   * Form element validation handler; Stores the new values in the form state.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   * @todo update
   */
  public static function instanceSettingsFormValidate(array $form, FormStateInterface $form_state) {
    if (isset($form_state['values']['instance'])) {
      unset($form_state['values']['instance']['settings']['handler_submit']);
      $form_state['instance']->settings = $form_state['values']['instance']['settings'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (empty($values['target_type']) && !empty($values['target_id'])) {
      throw new \InvalidArgumentException('No entity type was provided, value is not a valid entity.');
    }
    if (!empty($values['target_type'])) {
      $this->properties['entity']->getDataDefinition()->getTargetDefinition()->setEntityTypeId($values['target_type']);
    }
    if (isset($values) && !is_array($values)) {
      // Directly update the property instead of invoking the parent, so it can
      // handle objects and IDs.
      $this->properties['entity']->setValue($values, $notify);
      // If notify was FALSE, ensure the target_id property gets synched.
      if (!$notify) {
        $this->set('target_id', $this->properties['entity']->getTargetIdentifier(), FALSE);
      }
    }
    else {
      // Make sure that the 'entity' property gets set as 'target_id'.
      if (isset($values['target_id']) && isset($values['target_type']) && !isset($values['entity'])) {
        $values['entity'] = entity_load($values['target_type'], $values['target_id']);
      }
      parent::setValue($values, $notify);
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

}
