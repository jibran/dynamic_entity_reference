<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference/DynamicEntityReferenceController.
 */

namespace Drupal\dynamic_entity_reference;

use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
use Drupal\entity_reference\EntityReferenceAutocomplete;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines route controller for dynamic entity reference.
 */
class DynamicEntityReferenceController extends ControllerBase {

  /**
   * The entity query object.
   *
   * @var \Drupal\entity_reference\EntityReferenceAutocomplete
   */
  protected $entityReferenceAutocomplete;

  /**
   * Constructs a DynamicEntityReferenceController object.
   *
   * @param \Drupal\entity_reference\EntityReferenceAutocomplete $entity_reference_autocompletion
   *   The autocompletion helper for entity references.
   */
  public function __construct(EntityReferenceAutocomplete $entity_reference_autocompletion) {
    $this->entityReferenceAutocomplete = $entity_reference_autocompletion;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_reference.autocomplete')
    );
  }

  /**
   * Autocomplete the label of an entity.
   *
   * @param Request $request
   *   The request object that contains the typed tags.
   * @param string $field_name
   *   The name of the entity reference field.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_name
   *   The bundle name.
   * @param string $target_type
   *   The target entity type ID to search for results.
   * @param string $entity_id
   *   (optional) The entity ID the entity reference field is attached to.
   *   Defaults to ''.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The matched labels as json.
   */
  public function handleAutocomplete(Request $request, $field_name, $entity_type, $bundle_name, $target_type, $entity_id) {
    $definitions = $this->entityManager()->getFieldDefinitions($entity_type, $bundle_name);

    if (!isset($definitions[$field_name])) {
      throw new AccessDeniedHttpException();
    }
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $definitions[$field_name];
    $access_control_handler = $this->entityManager()->getAccessControlHandler($entity_type);
    if ($field_definition->getType() != 'dynamic_entity_reference' || !$access_control_handler->fieldAccess('edit', $field_definition)) {
      throw new AccessDeniedHttpException();
    }

    $settings = $field_definition->getSettings();
    $target_types = DynamicEntityReferenceItem::getAllEntityTypeIds($settings);
    if (!in_array($target_type, array_keys($target_types))) {
      throw new AccessDeniedHttpException();
    }

    // We put the dummy value here so selection plugins can work.
    // @todo Remove these once https://www.drupal.org/node/1959806
    //   and https://www.drupal.org/node/2107243 are fixed.
    $field_definition->settings['target_type'] = $target_type;
    $field_definition->settings['handler'] = $settings[$target_type]['handler'];
    $field_definition->settings['handler_settings'] = $settings[$target_type]['handler_settings'];

    // Get the typed string, if exists from the URL.
    $items_typed = $request->query->get('q');
    $items_typed = Tags::explode($items_typed);
    $last_item = Unicode::strtolower(array_pop($items_typed));

    $matches = $this->entityReferenceAutocomplete->getMatches($field_definition, $entity_type, $bundle_name, $entity_id, '', $last_item);

    return new JsonResponse($matches);
  }

}
