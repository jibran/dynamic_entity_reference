<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReference.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldType;

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;
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
    // @todo ad new 'dynamic_entity' typed data.
    $properties['entity'] = DataReferenceDefinition::create('entity')
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
  public function settingsForm(array &$form, array &$form_state, $has_data) {
    $element['target_type'] = array(
      '#type' => 'select',
      '#title' => t('Type of item to reference'),
      '#options' => \Drupal::entityManager()->getEntityTypeLabels(),
      '#default_value' => $this->getSetting('target_type'),
      '#required' => TRUE,
      '#disabled' => $has_data,
      '#size' => 1,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   * @todo update
   */
  public function instanceSettingsForm(array $form, array &$form_state) {
    $instance = $form_state['instance'];

    // Get all selection plugins for this entity type.
    $selection_plugins = \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionGroups($this->getSetting('target_type'));
    $handler_groups = array_keys($selection_plugins);

    $handlers = \Drupal::service('plugin.manager.entity_reference.selection')->getDefinitions();
    $handlers_options = array();
    foreach ($handlers as $plugin_id => $plugin) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default_node', 'default_user',
      // ...).
      if (in_array($plugin_id, $handler_groups)) {
        $handlers_options[$plugin_id] = check_plain($plugin['label']);
      }
    }

    $form = array(
      '#type' => 'container',
      '#attached' => array(
        'css' => array(drupal_get_path('module', 'entity_reference') . '/css/entity_reference.admin.css'),
      ),
      '#process' => array(
        '_entity_reference_field_instance_settings_ajax_process',
      ),
      '#element_validate' => array(array(get_class($this), 'instanceSettingsFormValidate')),
    );
    $form['handler'] = array(
      '#type' => 'details',
      '#title' => t('Reference type'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => array('_entity_reference_form_process_merge_parent'),
    );

    $form['handler']['handler'] = array(
      '#type' => 'select',
      '#title' => t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $instance->getSetting('handler'),
      '#required' => TRUE,
      '#ajax' => TRUE,
      '#limit_validation_errors' => array(),
    );
    $form['handler']['handler_submit'] = array(
      '#type' => 'submit',
      '#value' => t('Change handler'),
      '#limit_validation_errors' => array(),
      '#attributes' => array(
        'class' => array('js-hide'),
      ),
      '#submit' => array('entity_reference_settings_ajax_submit'),
    );

    $form['handler']['handler_settings'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('entity_reference-settings')),
    );

    $handler = \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionHandler($instance);
    $form['handler']['handler_settings'] += $handler->settingsForm($instance);

    return $form;
  }

  /**
   * Form element validation handler; Stores the new values in the form state.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param array $form_state
   *   The form state of the (entire) configuration form.
   * @todo update
   */
  public static function instanceSettingsFormValidate(array $form, array &$form_state) {
    if (isset($form_state['values']['instance'])) {
      unset($form_state['values']['instance']['settings']['handler_submit']);
      $form_state['instance']->settings = $form_state['values']['instance']['settings'];
    }
  }

}
