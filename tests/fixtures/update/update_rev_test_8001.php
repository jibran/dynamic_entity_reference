<?php

/**
 * @file
 * Contains database additions for testing the upgrade path 8201.
 *
 * This script is run from drupal root after installing the testing profile of
 * Drupal 8.2.x on 8.x-1.x branch of dynamic_entity_reference. For more details
 * see https://www.drupal.org/node/2555027#comment-11307815.
 */

use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\entity_test\Entity\EntityTestRev;

\Drupal::state()->set('dynamic_entity_reference_entity_test_entities', [
  'entity_test_rev',
  'entity_test_mulrev',
  'entity_test_string_id',
]);
\Drupal::state()->set('dynamic_entity_reference_entity_test_with_two_base_fields', TRUE);
\Drupal::state()->set('dynamic_entity_reference_entity_test_cardinality', 1);
\Drupal::state()->set('dynamic_entity_reference_entity_test_revisionable', TRUE);
\Drupal::service('module_installer')->install(['dynamic_entity_reference_entity_test']);

// Create some test entities which link each other.
$referenced_entity = EntityTestRev::create();
$referenced_entity->save();
$referenced_entity_mul = EntityTestMulRev::create();
$referenced_entity_mul->save();

$entity = EntityTestRev::create();
$entity->dynamic_references[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->dynamic_references[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();

$entity = EntityTestRev::create();
$entity->dynamic_references[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->dynamic_references[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();

$entity = EntityTestMulRev::create();
$entity->dynamic_references[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->dynamic_references[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();

$entity = EntityTestMulRev::create();
$entity->dynamic_references[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->dynamic_references[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity_mul;
$entity->setNewRevision(TRUE);
$entity->save();
$entity->der[0] = $referenced_entity;
$entity->setNewRevision(TRUE);
$entity->save();
