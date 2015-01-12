<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Tests\DynamicEntityReferenceFieldTest.
 */

namespace Drupal\dynamic_entity_reference\Tests;

use Drupal\config\Tests\SchemaCheckTestTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Tests for the dynamic entity reference field.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceFieldTest extends EntityUnitTestBase {
  use SchemaCheckTestTrait;

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The entity type that is being referenced.
   *
   * @var string
   */
  protected $referencedEntityType = 'entity_test_rev';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('dynamic_entity_reference');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');

    // Create a field.
    FieldStorageConfig::create(array(
      'field_name' => $this->fieldName,
      'type' => 'dynamic_entity_reference',
      'entity_type' => $this->entityType,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => array(
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => array(
          $this->referencedEntityType => $this->referencedEntityType,
        ),
      ),
    ))->save();

    FieldConfig::create(array(
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'label' => 'Field test',
      'settings' => array(),
    ))->save();

  }

  /**
   * Tests the multiple target entities loader.
   */
  public function testReferencedEntitiesMultipleLoad() {
    // Create the parent entity.
    $entity = entity_create($this->entityType, array('type' => $this->bundle));

    // Create three target entities and attach them to parent field.
    $target_entities = array();
    $reference_field = array();
    for ($i = 0; $i < 3; $i++) {
      $target_entity = entity_create($this->referencedEntityType, array('type' => $this->bundle));
      $target_entity->save();
      $target_entities[] = $target_entity;
      $reference_field[] = array('target_id' => $target_entity->id(), 'target_type' => $this->referencedEntityType);
    }

    // Also attach a non-existent entity and a NULL target id.
    $reference_field[3]['target_id'] = 99999;
    $reference_field[3]['target_type'] = $this->referencedEntityType;
    $target_entities[3] = NULL;
    $reference_field[4]['target_id'] = NULL;
    $reference_field[4]['target_type'] = $this->referencedEntityType;
    $target_entities[4] = NULL;

    // Also attach a non-existent entity and a NULL target id.
    $reference_field[5]['target_id'] = 99999;
    $reference_field[5]['target_type'] = $this->entityType;
    $target_entities[5] = NULL;
    $reference_field[6]['target_id'] = NULL;
    $reference_field[6]['target_type'] = NULL;
    $target_entities[6] = NULL;

    // Attach the first created target entity as the sixth item ($delta == 5) of
    // the parent entity field. We want to test the case when the same target
    // entity is referenced twice (or more times) in the same entity reference
    // field.
    $reference_field[7] = $reference_field[0];
    $target_entities[7] = $target_entities[0];

    // Create a new target entity that is not saved, thus testing the
    // "autocreate" feature.
    $target_entity_unsaved = entity_create($this->referencedEntityType, array('type' => $this->bundle, 'name' => $this->randomString()));
    $reference_field[8]['entity'] = $target_entity_unsaved;
    $target_entities[8] = $target_entity_unsaved;

    // Set the field value.
    $entity->{$this->fieldName}->setValue($reference_field);

    // Load the target entities using EntityReferenceField::referencedEntities().
    $entities = $entity->{$this->fieldName}->referencedEntities();

    // Test returned entities:
    // - Deltas must be preserved.
    // - Non-existent entities must not be retrieved in target entities result.
    foreach ($target_entities as $delta => $target_entity) {
      if (!empty($target_entity)) {
        if (!$target_entity->isNew()) {
          // There must be an entity in the loaded set having the same id for
          // the same delta.
          $this->assertEqual($target_entity->id(), $entities[$delta]->id());
        }
        else {
          // For entities that were not yet saved, there must an entity in the
          // loaded set having the same label for the same delta.
          $this->assertEqual($target_entity->label(), $entities[$delta]->label());
        }
      }
      else {
        // A non-existent or NULL entity target id must not return any item in
        // the target entities set.
        $this->assertFalse(isset($loaded_entities[$delta]));
      }
    }
  }

}
