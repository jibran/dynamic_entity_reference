<?php

/**
 * @file
 * Contains database additions for testing the upgrade path 8201.
 *
 * This script is run from drupal root after installing the testing profile of
 * Drupal 8.2.x on 8.x-1.x branch of dynamic_entity_reference. For more details
 * see https://www.drupal.org/node/2555027#comment-11307815.
 */

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

\Drupal::service('module_installer')->install([
  'dynamic_entity_reference_entity_test',
]);

$field_storage = FieldStorageConfig::create([
  'entity_type' => 'entity_test',
  'field_name' => 'field_test',
  'type' => 'dynamic_entity_reference',
  'settings' => [
    'exclude_entity_types' => FALSE,
    'entity_type_ids' => [
      'entity_test',
      'entity_test_mul',
      'entity_test_string_id',
    ],
  ],
  'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
]);
$field_storage->save();

$field = FieldConfig::create([
  'entity_type' => 'entity_test',
  'field_name' => 'field_test',
  'bundle' => 'entity_test',
  'settings' => [
    'entity_test' => [
      'handler' => "default:entity_test",
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ],
    'entity_test_mul' => [
      'handler' => "default:entity_test_mul",
      'handler_settings' => [
        'target_bundles' => [
          'entity_test_mul' => 'entity_test_mul',
        ],
      ],
    ],
    'entity_test_string_id' => [
      'handler' => "default:entity_test_string_id",
      'handler_settings' => [
        'target_bundles' => [
          'entity_test_string_id' => 'entity_test_string_id',
        ],
      ],
    ],
  ],
]);
$field->save();

$field_storage = FieldStorageConfig::create([
  'entity_type' => 'entity_test_mul',
  'field_name' => 'field_test_mul',
  'type' => 'dynamic_entity_reference',
  'settings' => [
    'exclude_entity_types' => FALSE,
    'entity_type_ids' => [
      'entity_test',
      'entity_test_mul',
      'entity_test_string_id',
    ],
  ],
  'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
]);
$field_storage->save();

$field = FieldConfig::create([
  'entity_type' => 'entity_test_mul',
  'field_name' => 'field_test_mul',
  'bundle' => 'entity_test_mul',
  'settings' => [
    'entity_test' => [
      'handler' => "default:entity_test",
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ],
    'entity_test_mul' => [
      'handler' => "default:entity_test_mul",
      'handler_settings' => [
        'target_bundles' => [
          'entity_test_mul' => 'entity_test_mul',
        ],
      ],
    ],
    'entity_test_string_id' => [
      'handler' => "default:entity_test_string_id",
      'handler_settings' => [
        'target_bundles' => [
          'entity_test_string_id' => 'entity_test_string_id',
        ],
      ],
    ],
  ],
]);
$field->save();


// Create some test entities which link each other.
$referenced_entity = EntityTest::create();
$referenced_entity->save();
$referenced_entity_mul = EntityTestMul::create();
$referenced_entity_mul->save();

$entity = EntityTest::create();
$entity->field_test[] = $referenced_entity;
$entity->field_test[] = $referenced_entity_mul;
$entity->dynamic_references[] = $referenced_entity;
$entity->save();

$entity = EntityTest::create();
$entity->field_test[] = $referenced_entity;
$entity->field_test[] = $referenced_entity_mul;
$entity->dynamic_references[] = $referenced_entity_mul;
$entity->save();


$entity = EntityTestMul::create();
$entity->field_test_mul[] = $referenced_entity;
$entity->field_test_mul[] = $referenced_entity_mul;
$entity->dynamic_references[] = $referenced_entity;
$entity->save();

$entity = EntityTestMul::create();
$entity->field_test_mul[] = $referenced_entity;
$entity->field_test_mul[] = $referenced_entity_mul;
$entity->dynamic_references[] = $referenced_entity_mul;
$entity->save();
