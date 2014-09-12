<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceWidget.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dynamic_entity_reference\DynamicEntityReferenceController;
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

    // Prepare the autocomplete route parameters.
    $autocomplete_route_parameters = array(
      'field_name' => $this->fieldDefinition->getName(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle_name' => $entity->bundle(),
      'target_type' => $items->get($delta)->target_type,
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

    // @todo inject this.
    $labels = \Drupal::entityManager()->getEntityTypeLabels(TRUE);
    $options = $labels['Content'];
    $entity_type_ids = $items->getSetting('entity_type_ids');
    if ($items->getSetting('exclude_entity_types')) {
      $available = array_diff_key($options, $entity_type_ids ?: array());
    }
    else {
      $available = array_intersect_key($options, $entity_type_ids ?: array());
    }
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

    return array(
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
  }

  /**
   * Checks whether a content entity is referenced.
   *
   * @param string $target_type
   *   The value target entity type
   * @return bool
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
      $values = $form_state['values'][$element['#field_name']][$element['#delta']];
      // Take "label (entity id)', match the id from parenthesis.
      if ($this->isContentReferenced($values['target_type']) && preg_match("/.+\((\d+)\)/", $element['#value'], $matches)) {
        $value = $matches[1];
      }
      elseif (preg_match("/.+\(([\w.]+)\)/", $element['#value'], $matches)) {
        $value = $matches[1];
      }
      if (!$value) {
        // Try to get a match from the input string when the user didn't use the
        // autocomplete but filled in a value manually.
        $value = $this->validateAutocompleteInput($values['target_type'], $element['#value'], $element, $form_state, $form);
      }

    }
    $form_state->setValueForElement($element, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function validateAutocompleteInput($target_type, $input, &$element, FormStateInterface $form_state, $form) {
    // @todo Make this a service.
    $controller = new DynamicEntityReferenceController(\Drupal::service('entity.query'));
    $bundled_entities = $controller->getReferenceableEntities($target_type, $input, '=', 6);
    $params = array(
      '%value' => $input,
      '@value' => $input,
    );
    $entities = array();
    foreach ($bundled_entities as $entities_list) {
      $entities += $entities_list;
    }
    if (empty($entities)) {
      // Error if there are no entities available for a required field.
      $form_state->setError($element, t('There are no entities matching "%value".', $params));
    }
    elseif (count($entities) > 5) {
      $params['@id'] = key($entities);
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many entities are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($entities) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = array();
      foreach ($entities as $id => $name) {
        $multiples[] = $name . ' (' . $id . ')';
      }
      $params['@id'] = $id;
      $params['%multiple'] = implode('", "', $multiples);
      $form_state->setError($element, t('Multiple entities match this reference; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    else {
      // Take the one and only matching entity.
      return key($entities);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityType(FieldItemListInterface $items, $delta) {
    // The autocomplete widget outputs one entity label per form element.
    if (isset($items[$delta])) {
      return $items[$delta]->target_type;
    }

    return FALSE;
  }

}
