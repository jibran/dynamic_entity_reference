<?php

/**
 * @file
 * Contains page callbacks for the module.
 */

/**
 * Dynamic entity reference auto-complete callback.
 */
function dynamic_entity_reference_autocomplete($target_type = NULL, $string = NULL) {
  if ($target_type == NULL) {
    drupal_json_output(array());
  }
  $match_operator = 'CONTAINS';
  // Get the typed string, if exists from the URL.
  $match = drupal_strtolower($string);

  $options = dynamic_entity_reference_get_referenceable_entities($target_type, $match, $match_operator, 10);

  $matches = array();
  // Loop through the entities and convert them into autocomplete output.
  foreach ($options as $values) {
    foreach ($values as $entity_id => $label) {
      $key = "$label ($entity_id)";
      // Strip things like starting/trailing white spaces, line breaks and
      // tags.
      $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(decode_entities(strip_tags($key)))));
      // Names containing commas or quotes must be wrapped in quotes.
      $matches[$key] = '<div class="reference-autocomplete">' . $label . '</div>';
    }
  }

  drupal_json_output($matches);
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
 * @return SelectQueryInterface
 *   The EntityQuery object with the basic conditions and sorting applied to
 *   it.
 */
function dynamic_entity_reference_build_entity_query($target_type, $match = NULL, $match_operator = 'CONTAINS') {

  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', $target_type);

  $entity_info = entity_get_info($target_type);
  if (isset($match) && isset($entity_info['entity keys']['label']) && $label_key = $entity_info['entity keys']['label']) {
    $query->propertyCondition($label_key, $match, $match_operator);
    $query->propertyOrderBy($label_key, 'ASC');
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
 * @return array
 *   Array of entity labels keyed by bundle and ID.
 */
function dynamic_entity_reference_get_referenceable_entities($target_type, $match, $match_operator, $limit) {

  $query = dynamic_entity_reference_build_entity_query($target_type, $match, $match_operator);
  $query->range(0, $limit);

  $result = $query->execute();

  if (empty($result) || empty($result[$target_type])) {
    return array();
  }

  $options = array();

  $entities = entity_load($target_type, array_keys($result[$target_type]));
  foreach ($entities as $entity_id => $entity) {
    /** @var \EntityDrupalWrapper $wrapper */
    $wrapper = entity_metadata_wrapper($target_type, $entity);
    $bundle = $wrapper->getBundle();
    $options[$bundle][$entity_id] = check_plain($wrapper->label());
  }
  return $options;
}
