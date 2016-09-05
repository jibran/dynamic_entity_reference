<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\config\Tests\SchemaCheckTestTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests for the dynamic entity reference field for revisionable entities.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceFieldRevisionTest extends EntityKernelTestBase {
  use SchemaCheckTestTrait;

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test_rev';

  /**
   * The entity type that is being referenced.
   *
   * @var string
   */
  protected $referencedEntityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test_rev';

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
  public static $modules = ['dynamic_entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema($this->entityType);

    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'dynamic_entity_reference',
      'entity_type' => $this->entityType,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          $this->referencedEntityType,
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'label' => 'Field test',
      'settings' => [],
    ])->save();

  }

  /**
   * Tests referencing entities for revisionable entities.
   */
  public function testReferenceForRevisionableEntities() {
    $field_name = $this->fieldName;
    $referenced_entity_1 = EntityTest::create();
    $referenced_entity_2 = EntityTest::create();
    $referenced_entity_1->save();
    $referenced_entity_2->save();
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType);
    /** @var \Drupal\entity_test\Entity\EntityTestRev $entity */
    $entity = $storage->create(['type' => $this->bundle]);
    // Set the field value.
    $entity->{$field_name}->setValue([
      [
        'target_id' => $referenced_entity_1->id(),
        'target_type' => $referenced_entity_1->getEntityTypeId(),
      ],
    ]);
    $entity->save();
    $entity_old = $storage->loadUnchanged($entity->id());
    $revision_id_1 = $entity->getRevisionId();
    $entity->setNewRevision(TRUE);
    // Set the field value.
    $entity->{$field_name}->setValue([
      [
        'target_id' => $referenced_entity_2->id(),
        'target_type' => $referenced_entity_2->getEntityTypeId(),
      ],
    ]);
    $entity->save();
    $revision_id_2 = $entity->getRevisionId();
    $entity_new = $storage->loadUnchanged($entity->id());
    $this->assertNotEquals($revision_id_1, $revision_id_2);
    $this->assertEquals($entity_old->id(), $entity_new->id());
    $this->assertEquals($entity_old->{$field_name}->target_id, $referenced_entity_1->id());
    $this->assertEquals($entity_new->{$field_name}->target_id, $referenced_entity_2->id());
    $storage->resetCache();
    $revision_1 = $storage->loadRevision($revision_id_1);
    $revision_2 = $storage->loadRevision($revision_id_2);
    $this->assertEquals($revision_1->{$field_name}->target_id, $referenced_entity_1->id());
    $this->assertEquals($revision_2->{$field_name}->target_id, $referenced_entity_2->id());
    $database = $this->container->get('database');
    $this->assertEquals([2], $database->query('SELECT field_test_target_id FROM {entity_test_rev__field_test} ORDER BY entity_id, revision_id, delta')->fetchCol());
    $this->assertEquals([2], $database->query('SELECT field_test_target_id_int FROM {entity_test_rev__field_test} ORDER BY entity_id, revision_id, delta')->fetchCol());
    $this->assertEquals([1, 2], $database->query('SELECT field_test_target_id FROM {entity_test_rev_revision__field_test} ORDER BY entity_id, revision_id, delta')->fetchCol());
    $this->assertEquals([1, 2], $database->query('SELECT field_test_target_id_int FROM {entity_test_rev_revision__field_test} ORDER BY entity_id, revision_id, delta')->fetchCol());
  }

}
