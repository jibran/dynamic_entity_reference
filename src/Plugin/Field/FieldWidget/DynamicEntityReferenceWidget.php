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

    $available = DynamicEntityReferenceItem::getTargetTypes($this->getFieldSettings());
    $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();

    // Prepare the autocomplete route parameters.
    $autocomplete_route_parameters = array(
      'field_name' => $this->fieldDefinition->getName(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle_name' => $entity->bundle(),
      'target_type' => $items->get($delta)->target_type ?: key($available),
    );

    if ($entity_id = $entity->id()) {
      $autocomplete_route_parameters['entity_id'] = $entity_id;
    }

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
    if ($target_type === NULL) {
      return parent::isContentReferenced();
    }
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
      $auto_create = $this->getSelectionHandlerSetting('auto_create', $values['target_type']);
      // Try to get a match from the input string when the user didn't use the
      // autocomplete but filled in a value manually.
      if ($value === NULL) {
        /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
        $handler = \Drupal::service('plugin.manager.dynamic_entity_reference_selection')->getSelectionHandler($this->fieldDefinition, NULL, $values['target_type']);
        $value = $handler->validateAutocompleteInput($element['#value'], $element, $form_state, $form, !$auto_create);
      }
      // Auto-create item. See
      // \Drupal\Core\Field\Plugin\Field\FieldType\DynamicEntityReferenceItem::presave().
      if (!$value && $auto_create && (count($this->getSelectionHandlerSetting('target_bundles', $values['target_type'])) == 1)) {
        $value = array(
          'target_id' => NULL,
          'entity' => $this->createNewEntity($element['#value'], $element['#autocreate_uid'], $values['target_type']),
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
   * Returns the value of a setting for the dynamic entity reference handler.
   *
   * @param string $setting_name
   *   The setting name.
   * @param string $target_type
   *   The id of the target entity type.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getSelectionHandlerSetting($setting_name, $target_type = NULL) {
    if ($target_type === NULL) {
      return parent::getSelectionHandlerSetting($setting_name);
    }
    $settings = $this->getFieldSettings();
    return isset($settings[$target_type]['handler_settings'][$setting_name]) ? $settings[$target_type]['handler_settings'][$setting_name] : NULL;
  }

  /**
   * Creates a new entity from a label entered in the autocomplete input.
   *
   * @param string $label
   *   The entity label.
   * @param int $uid
   *   The entity uid.
   * @param string $target_type
   *   The target entity type id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The newly created entity.
   */
  protected function createNewEntity($label, $uid, $target_type = NULL) {
    if ($target_type === NULL) {
      return parent::createNewEntity($label, $uid);
    }
    $entity_manager = \Drupal::entityManager();
    $target_bundles = $this->getSelectionHandlerSetting('target_bundles', $target_type);

    // Get the bundle.
    if (!empty($target_bundles)) {
      $bundle = reset($target_bundles);
    }
    else {
      $bundles = $entity_manager->getBundleInfo($target_type);
      $bundle = reset($bundles);
    }

    $entity_type = $entity_manager->getDefinition($target_type);
    $bundle_key = $entity_type->getKey('bundle');
    $label_key = $entity_type->getKey('label');

    $entity = $entity_manager->getStorage($target_type)->create(array(
      $label_key => $label,
      $bundle_key => $bundle,
    ));

    if ($entity instanceof EntityOwnerInterface) {
      $entity->setOwnerId($uid);
    }

    return $entity;
  }

}
