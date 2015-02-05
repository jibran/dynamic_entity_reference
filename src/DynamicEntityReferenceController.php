<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference/DynamicEntityReferenceController.
 */

namespace Drupal\dynamic_entity_reference;

use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
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
   * @var \Drupal\Core\Entity\EntityAutocompleteMatcher
   */
  protected $matcher;

  /**
   * Constructs a DynamicEntityReferenceController object.
   *
   * @param \Drupal\Core\Entity\EntityAutocompleteMatcher $matcher
   *   The autocomplete matcher for entity references.
   */
  public function __construct(EntityAutocompleteMatcher $matcher) {
    $this->matcher = $matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.autocomplete_matcher')
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
    $matches = array();
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
    $target_types = DynamicEntityReferenceItem::getTargetTypes($settings);
    if (!in_array($target_type, array_keys($target_types))) {
      throw new AccessDeniedHttpException();
    }
    $selection_handler = $settings[$target_type]['handler'];
    $selection_settings = $settings[$target_type]['handler_settings'];

    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      $typed_string = Tags::explode($input);
      $typed_string = Unicode::strtolower(array_pop($typed_string));

      // Selection settings are passed in as an encoded serialized array.
      $selection_settings = $selection_settings ? unserialize(base64_decode($selection_settings)) : array();

      $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, $typed_string);
    }

    return new JsonResponse($matches);
  }

}
