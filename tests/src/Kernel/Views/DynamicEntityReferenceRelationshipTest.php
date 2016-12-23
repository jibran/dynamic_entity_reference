<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel\Views;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests dynamic entity reference relationship data.
 *
 * @group dynamic_entity_reference
 *
 * @see dynamic_entity_reference_field_views_data()
 */
class DynamicEntityReferenceRelationshipTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_dynamic_entity_reference_entity_test_view',
    'test_dynamic_entity_reference_entity_test_mul_view',
    'test_dynamic_entity_reference_entity_test_rev_view',
  ];

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'user',
    'field',
    'entity_test',
    'dynamic_entity_reference',
    'dynamic_entity_reference_test_views',
  ];

  /**
   * The entity_test entities used by the test.
   *
   * @var array
   */
  protected $entities = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'type' => 'dynamic_entity_reference',
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'entity_test',
          'entity_test_mul',
        ],
      ],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
      'settings' => [],
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
        ],
      ],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_test_mul',
      'bundle' => 'entity_test_mul',
      'settings' => [],
    ]);
    $field->save();

    ViewTestData::createTestViews(get_class($this), ['dynamic_entity_reference_test_views']);
  }

  /**
   * Tests views relationship with multiple referenced entities.
   */
  public function testMultipleRelationship() {
    // Create some test entities which link each other.
    $referenced_entity = EntityTest::create();
    $referenced_entity->save();
    $referenced_entity_mul = EntityTestMul::create();
    $referenced_entity_mul->save();

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity;
    $entity->field_test[] = $referenced_entity_mul;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_mul->id());
    $this->entities[] = $entity;

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity;
    $entity->field_test[] = $referenced_entity_mul;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_mul->id());
    $this->entities[] = $entity;

    Views::viewsData()->clear();

    // Check just the generated views data.
    $views_data_field_test = Views::viewsData()->get('entity_test__field_test');

    // Check views data for test entity referenced from field_test.
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['base'], 'entity_test');
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['entity type'], 'entity_test');
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['relationship field'], 'field_test_target_id_int');
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['extra'][0]['left_field'], 'field_test_target_type');
    $this->assertEquals($views_data_field_test['entity_test__field_test']['relationship']['extra'][0]['value'], 'entity_test');
    $this->assertEquals($views_data_field_test['field_test_target_id']['argument']['id'], 'numeric');
    $this->assertEquals($views_data_field_test['field_test_target_id']['filter']['id'], 'numeric');

    // Check views data for test entity - data table referenced from field_test.
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['entity type'], 'entity_test_mul');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['relationship field'], 'field_test_target_id_int');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['extra'][0]['left_field'], 'field_test_target_type');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test']['relationship']['extra'][0]['value'], 'entity_test_mul');
    $this->assertEquals($views_data_field_test['field_test_target_id']['argument']['id'], 'numeric');
    $this->assertEquals($views_data_field_test['field_test_target_id']['filter']['id'], 'numeric');

    // Check the backwards reference for test entity using field_test.
    $views_data_entity_test = Views::viewsData()->get('entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['base'], 'entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['field table'], 'entity_test__field_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['field field'], 'field_test_target_id_int');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][0]['field'], 'field_test_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][0]['value'], 'entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Check the backwards reference for test entity - data table using
    // field_test.
    $views_data_entity_test = Views::viewsData()->get('entity_test_mul_property_data');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['base'], 'entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['field table'], 'entity_test__field_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['field field'], 'field_test_target_id_int');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][0]['field'], 'field_test_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][0]['value'], 'entity_test_mul');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Check an actual test view.
    $view = Views::getView('test_dynamic_entity_reference_entity_test_view');
    $this->executeView($view);

    foreach ($view->result as $index => $row) {
      // Just check that the actual ID of the entity is the expected one.
      $this->assertEquals($row->id, $this->entities[$index]->id());
      // Also check that we have the correct result entity.
      $this->assertEquals($row->_entity->id(), $this->entities[$index]->id());
      $this->assertEquals($row->_entity->bundle(), $this->entities[$index]->bundle());
      // Test the relationship.
      $this->assertEquals($row->entity_test_entity_test__field_test_id, 1);

      // Test that the correct relationship entity is on the row.
      $this->assertEquals($row->_relationship_entities['entity_test__field_test']->id(), 1);
      $this->assertEquals($row->_relationship_entities['entity_test__field_test']->bundle(), 'entity_test');
    }

    $view->destroy();
    $view->setDisplay('embed_1');
    $this->executeView($view);

    foreach ($view->result as $index => $row) {
      // Just check that the actual ID of the entity is the expected one.
      $this->assertEquals($row->id, $this->entities[$index]->id());
      // Also check that we have the correct result entity.
      $this->assertEquals($row->_entity->id(), $this->entities[$index]->id());
      $this->assertEquals($row->_entity->bundle(), $this->entities[$index]->bundle());
      // Test the relationship.
      $this->assertEquals($row->entity_test_mul_property_data_entity_test__field_test_id, 1);

      // Test that the correct relationship entity is on the row.
      $this->assertEquals($row->_relationship_entities['entity_test_mul__field_test']->id(), 1);
      $this->assertEquals($row->_relationship_entities['entity_test_mul__field_test']->bundle(), 'entity_test_mul');
    }
  }

  /**
   * Tests views reverse relationship with multiple referenced entities.
   */
  public function testReverseMultipleRelationship() {
    // Create some test entities which link each other.
    $referenced_entity = EntityTest::create();
    $referenced_entity->save();
    $referenced_entity_mul = EntityTestMul::create();
    $referenced_entity_mul->save();

    $entity = EntityTestMul::create();
    $entity->field_test_mul[] = $referenced_entity;
    $entity->field_test_mul[] = $referenced_entity_mul;
    $entity->save();
    $this->assertEquals($entity->field_test_mul[0]->entity->id(), $referenced_entity->id());
    $this->assertEquals($entity->field_test_mul[1]->entity->id(), $referenced_entity_mul->id());
    $this->entities[] = $entity;

    $entity = EntityTestMul::create();
    $entity->field_test_mul[] = $referenced_entity;
    $entity->field_test_mul[] = $referenced_entity_mul;
    $entity->save();
    $this->assertEquals($entity->field_test_mul[0]->entity->id(), $referenced_entity->id());
    $this->assertEquals($entity->field_test_mul[1]->entity->id(), $referenced_entity_mul->id());
    $this->entities[] = $entity;

    Views::viewsData()->clear();

    // Check just the generated views data.
    $views_data_field_test = Views::viewsData()->get('entity_test_mul__field_test_mul');

    // Check views data for test entity referenced from field_test_mul.
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['base'], 'entity_test');
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['entity type'], 'entity_test');
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['relationship field'], 'field_test_mul_target_id_int');
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['extra'][0]['left_field'], 'field_test_mul_target_type');
    $this->assertEquals($views_data_field_test['entity_test__field_test_mul']['relationship']['extra'][0]['value'], 'entity_test');
    $this->assertEquals($views_data_field_test['field_test_mul_target_id']['argument']['id'], 'numeric');
    $this->assertEquals($views_data_field_test['field_test_mul_target_id']['filter']['id'], 'numeric');

    // Check views data for test entity - data table referenced from
    // field_test_mul.
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['entity type'], 'entity_test_mul');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['relationship field'], 'field_test_mul_target_id_int');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['extra'][0]['left_field'], 'field_test_mul_target_type');
    $this->assertEquals($views_data_field_test['entity_test_mul__field_test_mul']['relationship']['extra'][0]['value'], 'entity_test_mul');
    $this->assertEquals($views_data_field_test['field_test_mul_target_id']['argument']['id'], 'numeric');
    $this->assertEquals($views_data_field_test['field_test_mul_target_id']['filter']['id'], 'numeric');

    // Check the backwards reference for test entity using field_test.
    $views_data_entity_test = Views::viewsData()->get('entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['field table'], 'entity_test_mul__field_test_mul');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['field field'], 'field_test_mul_target_id_int');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][0]['field'], 'field_test_mul_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][0]['value'], 'entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Check the backwards reference for test entity - data table using
    // field_test.
    $views_data_entity_test = Views::viewsData()->get('entity_test_mul_property_data');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['field table'], 'entity_test_mul__field_test_mul');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['field field'], 'field_test_mul_target_id_int');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][0]['field'], 'field_test_mul_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][0]['value'], 'entity_test_mul');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Check an actual test view.
    $view = Views::getView('test_dynamic_entity_reference_entity_test_mul_view');
    $this->executeView($view);

    foreach ($view->result as $index => $row) {
      $this->assertEquals($row->id, 1);
      $this->assertEquals($row->_entity->id(), 1);
      $this->assertEquals($row->_entity->bundle(), 'entity_test_mul');

      // Test the backwards relationship.
      $this->assertEquals($row->field_test_mul_entity_test_mul_property_data_id, $this->entities[$index]->id());

      // Test that the correct relationship entity is on the row.
      $this->assertEquals($row->_relationship_entities['reverse__entity_test_mul__field_test_mul']->id(), $this->entities[$index]->id());
      $this->assertEquals($row->_relationship_entities['reverse__entity_test_mul__field_test_mul']->bundle(), 'entity_test_mul');

    }

    $view = Views::getView('test_dynamic_entity_reference_entity_test_rev_view');
    $this->executeView($view);

    foreach ($view->result as $index => $row) {
      $this->assertEquals($row->id, 1);
      $this->assertEquals($row->_entity->id(), 1);
      $this->assertEquals($row->_entity->bundle(), 'entity_test');

      // Test the backwards relationship.
      $this->assertEquals($row->field_test_mul_entity_test_id, $this->entities[$index]->id());

      // Test that the correct relationship entity is on the row.
      $this->assertEquals($row->_relationship_entities['reverse__entity_test_mul__field_test_mul']->id(), $this->entities[$index]->id());
      $this->assertEquals($row->_relationship_entities['reverse__entity_test_mul__field_test_mul']->bundle(), 'entity_test_mul');
    }
  }

  /**
   * Tests views relationship with entity with string id referenced entities.
   */
  public function testStringIdRelationship() {
    \Drupal::state()->set('dynamic_entity_reference_test_views.entity_test_string_id.view', TRUE);
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->installEntitySchema('entity_test_string_id');
    $field_storage_settings = [
      'exclude_entity_types' => FALSE,
      'entity_type_ids' => [
        'entity_test_string_id',
      ],
    ];
    $field_settings = [
      'entity_test_string_id' => [
        'handler' => 'default:entity_test_string_id',
        'handler_settings' => [
          'target_bundles' => NULL,
        ],
      ],
    ];
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_test');
    $field_storage->setSettings($field_storage_settings)
      ->save();
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_test');
    $field->setSettings($field_settings)
      ->save();
    $field_storage = FieldStorageConfig::loadByName('entity_test_mul', 'field_test_mul');
    $field_storage->setSettings($field_storage_settings)
      ->save();
    $field = FieldConfig::loadByName('entity_test_mul', 'entity_test_mul', 'field_test_mul');
    $field->setSettings($field_settings)
      ->save();

    // Clears views data caches.
    Views::viewsData()->clear();

    // Check just the generated views data.
    $views_data_field_test = Views::viewsData()->get('entity_test__field_test');

    // Check views data for string id test entity referenced from field_test.
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['base'], 'entity_test_string');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['entity type'], 'entity_test_string_id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['relationship field'], 'field_test_target_id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['extra'][0]['left_field'], 'field_test_target_type');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test']['relationship']['extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_field_test['field_test_target_id']['argument']['id'], 'string');
    $this->assertEquals($views_data_field_test['field_test_target_id']['filter']['id'], 'string');

    // Check the backwards reference for test entity using field_test.
    $views_data_entity_test = Views::viewsData()->get('entity_test_string');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['base'], 'entity_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['field table'], 'entity_test__field_test');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['field field'], 'field_test_target_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][0]['field'], 'field_test_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test__field_test']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Check just the generated views data.
    $views_data_field_test = Views::viewsData()->get('entity_test_mul__field_test_mul');

    // Views data for string id test entity referenced from field_test_mul.
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['base'], 'entity_test_string');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['entity type'], 'entity_test_string_id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['relationship field'], 'field_test_mul_target_id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['extra'][0]['left_field'], 'field_test_mul_target_type');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_mul']['relationship']['extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_field_test['field_test_mul_target_id']['argument']['id'], 'string');
    $this->assertEquals($views_data_field_test['field_test_mul_target_id']['filter']['id'], 'string');

    // Check the backwards reference for test entity mul using field_test_mul.
    $views_data_entity_test = Views::viewsData()->get('entity_test_string');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['field table'], 'entity_test_mul__field_test_mul');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['field field'], 'field_test_mul_target_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][0]['field'], 'field_test_mul_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test_mul__field_test_mul']['relationship']['join_extra'][1]['numeric'], TRUE);
  }

  /**
   * Tests views relationship with string id entity with string id reference.
   */
  public function testStringIdReverseRelationship() {
    \Drupal::state()->set('dynamic_entity_reference_test_views.entity_test_string_id.view', TRUE);
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->installEntitySchema('entity_test_string_id');

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_string_id',
      'field_name' => 'field_test_string_id',
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
      'entity_type' => 'entity_test_string_id',
      'field_name' => 'field_test_string_id',
      'bundle' => 'entity_test_mul',
      'settings' => [],
    ]);
    $field->save();
    // Clears views data caches.
    Views::viewsData()->clear();

    // Check just the generated views data.
    $views_data_field_test = Views::viewsData()->get('entity_test_string_id__field_test_string_id');

    // ViewsData for string id test entity referenced from field_test_string_id.
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['id'], 'standard');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['base'], 'entity_test_string');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['entity type'], 'entity_test_string_id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['relationship field'], 'field_test_string_id_target_id');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['extra'][0]['left_field'], 'field_test_string_id_target_type');
    $this->assertEquals($views_data_field_test['entity_test_string_id__field_test_string_id']['relationship']['extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_field_test['field_test_string_id_target_id']['argument']['id'], 'string');
    $this->assertEquals($views_data_field_test['field_test_string_id_target_id']['filter']['id'], 'string');

    // Backwards reference for string id test entity using field_test_string_id.
    $views_data_entity_test = Views::viewsData()->get('entity_test_string');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['base'], 'entity_test_string');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['field table'], 'entity_test_string_id__field_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['field field'], 'field_test_string_id_target_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][0]['field'], 'field_test_string_id_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Check just the generated views data.
    $views_data_field_test = Views::viewsData()->get('entity_test');

    // Backwards reference for string id test entity using field_test_string_id.
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['base'], 'entity_test_string');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['field table'], 'entity_test_string_id__field_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['field field'], 'field_test_string_id_target_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][0]['field'], 'field_test_string_id_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][0]['value'], 'entity_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['numeric'], TRUE);

    // Backwards reference for string id test entity using field_test_string_id.
    $views_data_entity_test = Views::viewsData()->get('entity_test_mul_property_data');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['id'], 'entity_reverse');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['base'], 'entity_test_string');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['base field'], 'id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['field table'], 'entity_test_string_id__field_test_string_id');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['field field'], 'field_test_string_id_target_id_int');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][0]['field'], 'field_test_string_id_target_type');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][0]['value'], 'entity_test_mul');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['field'], 'deleted');
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['value'], 0);
    $this->assertEquals($views_data_entity_test['reverse__entity_test_string_id__field_test_string_id']['relationship']['join_extra'][1]['numeric'], TRUE);
  }

}
