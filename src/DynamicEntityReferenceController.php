<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference/DynamicEntityReferenceController.
 */

namespace Drupal\dynamic_entity_reference;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines route controller for dynamic entity reference.
 */
class DynamicEntityReferenceController extends ControllerBase {

  /**
   * The entity query object.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory $entity_query
   */
  protected $entityQuery;

  /**
   * Constructs a new route controller for entity reference.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query object.
   */
  public function __construct(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
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
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The matched labels as json.
   */
  public function handleAutocomplete(Request $request, $field_name, $entity_type, $bundle_name, $target_type) {
    $form_mode = 'default';
    $widget = $this->entityManager()
      ->getStorage('entity_form_display')
      ->load($entity_type . '.' . $bundle_name . '.' . $form_mode)
      ->getComponent($field_name);
    $match_operator = !empty($widget['settings']['match_operator']) ? $widget['settings']['match_operator'] : 'CONTAINS';
    // Get the typed string, if exists from the URL.
    $items_typed = $request->query->get('q');
    $items_typed = Tags::explode($items_typed);
    $match = Unicode::strtolower(array_pop($items_typed));

    $options = $this->getReferenceableEntities($target_type, $match, $match_operator, 10);

    $matches = array();
    // Loop through the entities and convert them into autocomplete output.
    foreach ($options as $values) {
      foreach ($values as $entity_id => $label) {
        $key = "$label ($entity_id)";
        // Strip things like starting/trailing white spaces, line breaks and
        // tags.
        $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(String::decodeEntities(strip_tags($key)))));
        // Names containing commas or quotes must be wrapped in quotes.
        $key = Tags::encode($key);
        $matches[] = array('value' => $key, 'label' => $label);
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Builds an EntityQuery to get referenceable entities.
   *
   * @param string $target_type
   *   The target entity type.
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The EntityQuery object with the basic conditions and sorting applied to
   *   it.
   */
  protected function buildEntityQuery($target_type, $match = NULL, $match_operator = 'CONTAINS') {

    $entity_type = $this->entityManager()->getDefinition($target_type);
    $query = $this->entityQuery->get($target_type);

    if (isset($match) && $label_key = $entity_type->getKey('label')) {
      $query->condition($label_key, $match, $match_operator);
      $query->sort($label_key, 'ASC');
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');

    return $query;
  }

  /**
   * Gets referenceable entities.
   *
   * @param string $target_type
   *   The target entity type.
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   * @param int $limit
   *   The query limit.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The EntityQuery object with the basic conditions and sorting applied to
   *   it.
   */
  public function getReferenceableEntities($target_type, $match, $match_operator, $limit) {

    $query = $this->buildEntityQuery($target_type, $match, $match_operator);
    $query->range(0, $limit);

    $result = $query->execute();

    if (empty($result)) {
      return array();
    }

    $options = array();
    /* @var \Drupal\Core\Entity\EntityInterface[] $entities */
    $entities = $this->entityManager()->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = String::checkPlain($entity->label());
    }
    return $options;
  }
}
