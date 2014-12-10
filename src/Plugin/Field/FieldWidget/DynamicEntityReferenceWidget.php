<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceWidget.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
use Drupal\entity_reference\Plugin\Field\FieldWidget\AutocompleteWidget;
use Drupal\user\EntityOwnerInterface;

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

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $target = $items->get($delta)->entity;

    $available = DynamicEntityReferenceItem::getAllEntityTypeIds($this->getFieldSettings());
    $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();

    // Prepare the autocomplete route parameters.
    $autocomplete_route_parameters = array(
      'field_name' => $this->fieldDefinition->getName(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle_name' => $entity->bundle(),
      'target_type' => $items->get($delta)->target_type ?: key($available),
    );

    $element += array(
      '#type' => 'textfield',
      '#maxlength' => 1024,
      '#default_value' => $target ? $target->label() . ' (' . $target->id() . ')' : '',
      '#autocomplete_route_name' => 'dynamic_entity_reference.autocomplete',
      '#autocomplete_route_parameters' => $autocomplete_route_parameters,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#element_validate' => array(array($this, 'elementValidate')),
      '#autocreate_uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : \Drupal::currentUser()->id(),
      '#field_name' => $items->getName(),
    );

    $element['#title'] = $this->t('Label');

    $entity_type = array(
      '#type' => 'select',
      '#options' => $available,
      '#title' => $this->t('Entity type'),
      '#default_value' => $items->get($delta)->target_type,
      '#weight' => -50,
      '#attributes' => array(
        'class' => array('dynamic-entity-reference-entity-type'),
      ),
    );

    $form_element = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('container-inline'),
      ),
      'target_type' => $entity_type,
      'target_id' => $element,
      '#attached' => array(
        'library' => array(
          'dynamic_entity_reference/drupal.dynamic_entity_reference_widget',
        ),
      ),
    );
    // Render field as details.
    if ($cardinality == 1) {
      $form_element['#type'] = 'details';
      $form_element['#title'] = $items->getFieldDefinition()->getLabel();
      $form_element['#open'] = TRUE;
    }
    return $form_element;
  }

  /**
   * Checks whether a content entity is referenced.
   *
   * @param string $target_type
   *   The value target entity type.
   *
   * @return bool
   *   TRUE if a content entity is referenced.
   */
  protected function isContentReferenced($target_type = NULL) {
    $target_type_info = \Drupal::entityManager()->getDefinition($target_type);
    return $target_type_info->isSubclassOf('\Drupal\Core\Entity\ContentEntityInterface');
  }

  /**
   * {@inheritdoc}
   */
  public function elementValidate($element, FormStateInterface $form_state, $form) {
    // If a value was entered into the autocomplete.
    $value = NULL;
    if (!empty($element['#value'])) {
      // If this is the default value of the field.
      if ($form_state->hasValue('default_value_input')) {
        $values = $form_state->getValue(array(
          'default_value_input',
          $element['#field_name'],
          $element['#delta'],
        ));
      }
      else {
        $values = $form_state->getValue(array(
          $element['#field_name'],
          $element['#delta'],
        ));
      }
      // Take "label (entity id)', match the id from parenthesis.
      if ($this->isContentReferenced($values['target_type']) && preg_match("/.+\((\d+)\)/", $element['#value'], $matches)) {
        $value = $matches[1];
      }
      elseif (preg_match("/.+\(([\w.]+)\)/", $element['#value'], $matches)) {
        $value = $matches[1];
      }
      $auto_create = $this->getHandlerSetting('auto_create', $values['target_type']);
      // Try to get a match from the input string when the user didn't use the
      // autocomplete but filled in a value manually.
      if ($value === NULL) {
        // To use entity_reference selection_handler for this target_type we
        // have to change these settings to entity_reference field settings.
        // @todo Remove these once https://www.drupal.org/node/1959806
        //   and https://www.drupal.org/node/2107243 are fixed.
        $this->fakeFieldSettings($values['target_type']);
        /** @var \Drupal\entity_reference\Plugin\Type\Selection\SelectionInterface $handler */
        $handler = \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionHandler($this->fieldDefinition);
        $value = $handler->validateAutocompleteInput($element['#value'], $element, $form_state, $form, !$auto_create);
      }
      // Auto-create item. See
      // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
      if (!$value && $auto_create && (count($this->getHandlerSetting('target_bundles', $values['target_type'])) == 1)) {
        $value = array(
          'target_id' => NULL,
          'entity' => $this->createNewEntity($element['#value'], $element['#autocreate_uid']),
          // Keep the weight property.
          '_weight' => $element['#weight'],
        );
        // Change the element['#parents'], so in form_set_value() we populate
        // the correct key.
        array_pop($element['#parents']);
      }

    }
    $form_state->setValueForElement($element, $value);
  }

  /**
   * Sets the fake field settings values.
   *
   * These settings are required by
   * \Drupal\entity_reference\Plugin\Type\SelectionPluginManager to select the
   * proper selection plugin and these settings are also used by
   * \Drupal\entity_reference\Plugin\entity_reference\selection\SelectionBase
   * @todo Remove these once https://www.drupal.org/node/1959806
   *   and https://www.drupal.org/node/2107243 are fixed.
   *
   * @param string $entity_type_id
   *   The id of the entity type.
   */
  protected function fakeFieldSettings($entity_type_id) {
    $settings = $this->getFieldSettings();
    $this->fieldDefinition->settings['target_type'] = $entity_type_id;
    $this->fieldDefinition->settings['handler'] = $settings[$entity_type_id]['handler'];
    $this->fieldDefinition->settings['handler_settings'] = $settings[$entity_type_id]['handler_settings'];
  }

  /**
   * Returns the value of a setting for the entity reference selection handler.
   *
   * @param string $setting_name
   *   The setting name.
   * @param string $entity_type_id
   *   The id of the entity type.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getHandlerSetting($setting_name, $entity_type_id) {
    $settings = $this->getFieldSettings();
    return isset($settings[$entity_type_id]['handler_settings'][$setting_name]) ? $settings[$entity_type_id]['handler_settings'][$setting_name] : NULL;
  }

}
