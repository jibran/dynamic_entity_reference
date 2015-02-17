<?php
/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceOptionsTrait.
 */

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget;


use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;

trait DynamicEntityReferenceOptionsTrait {

  /**
   * {@inheritdoc}
   *
   * This widget only support single target type dynamic entity reference
   * fields. Select list Check boxes and radio buttons don't make sense for
   * multiple target_types.
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return count(DynamicEntityReferenceItem::getTargetTypes($field_definition->getSettings())) == 1;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedOptions(FieldItemListInterface $items, $delta = 0) {
    // We need to check against a flat list of options.
    $flat_options = OptGroup::flattenOptions($this->getOptions($items->getEntity()));

    $selected_options = array();
    foreach ($items as $item) {
      $value = "{$item->target_type}-{$item->target_id}";
      // Keep the value if it actually is in the list of options (needs to be
      // checked against the flat list).
      if (isset($flat_options[$value])) {
        $selected_options[] = $value;
      }
    }

    return $selected_options;
  }

  /**
   * {@inheritdoc}
   *
   * To save both target_type and target_id the option value is split into
   * target_type and target_id.
   *
   * @see \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem::getSettableOptions()
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $index => $value) {
      list($values[$index]['target_type'], $values[$index]['target_id']) = explode('-', $value['target_id']);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this when https://www.drupal.org/node/2426781 is fixed.
   */
  protected function getOptions(FieldableEntityInterface $entity) {
    if (!isset($this->options)) {
      // Limit the settable options for the current user account.
      $options = $this->fieldDefinition
        ->getFieldStorageDefinition()
        ->getOptionsProvider($this->column, $entity)
        ->getSettableOptions(\Drupal::currentUser());

      // Add an empty option if the widget needs one.
      if ($empty_option = $this->getEmptyOption()) {
        switch ($this->getPluginId()) {
          case 'options_buttons':
            $label = t('N/A');
            break;

          case 'options_select':
            $label = ($empty_option == static::OPTIONS_EMPTY_NONE ? t('- None -') : t('- Select a value -'));
            break;

          default:
            $label = $this->getEmptyLabel();
        }

        $options = array('_none' => $label) + $options;
      }

      $module_handler = \Drupal::moduleHandler();
      $context = array(
        'fieldDefinition' => $this->fieldDefinition,
        'entity' => $entity,
      );
      $module_handler->alter('options_list', $options, $context);

      array_walk_recursive($options, array($this, 'sanitizeLabel'));

      // Options might be nested ("optgroups"). If the widget does not support
      // nested options, flatten the list.
      if (!$this->supportsGroups()) {
        $options = OptGroup::flattenOptions($options);
      }

      $this->options = $options;
    }
    return $this->options;
  }

  /**
   * Returns the empty option label .
   *
   * @return string|null
   *   Either string, or NULL.
   */
  protected function getEmptyLabel()  {
    $plugin_id = $this->getPluginId();
    if (strpos($plugin_id, 'buttons') !== FALSE) {
      $label = t('N/A');
    }
    else {
      $label = ($this->getEmptyOption() == static::OPTIONS_EMPTY_NONE ? t('- None -') : t('- Select a value -'));
    }
    return $label;
  }
}
