<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Component\Utility\Unicode;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Base field tests for referencing config entities.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceConfigEntityBaseFieldTest extends EntityKernelTestBase {

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The entity type that is being referenced.
   *
   * @var string[]
   */
  protected $referencedEntityTypes = [
    'config_test',
  ];

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
  protected $fieldName = 'dynamic_references';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * A config entity to reference.
   *
   * @var \Drupal\config_test\ConfigTestInterface
   */
  protected $configTestReference;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['config_test', 'dynamic_entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['config_test']);
    $this->installEntitySchema('entity_test_rev');

    $this->state = $this->container->get('state');

    // Setup some states for controlling the base field configuration.
    // @see dynamic_entity_reference_entity_test_entity_base_field_info()
    $this->state->set('dynamic_entity_reference_entity_test_cardinality', 1);
    $this->state->set('dynamic_entity_reference_entity_test_entities', [$this->entityType, 'config_test']);
    $this->state->set('dynamic_entity_reference_entity_test_exclude', [$this->entityType]);

    // Add a config entity for referencing.
    $this->configTestReference = $this->container->get('entity_type.manager')
      ->getStorage('config_test')
      ->create([
        'id' => Unicode::strtolower($this->randomMachineName()),
        'label' => $this->randomString(),
        'style' => Unicode::strtolower($this->randomMachineName()),
      ]);
    $this->configTestReference->save();
  }

  /**
   * Config entity only base DER field.
   */
  public function testBaseField() {
    // @see dynamic_entity_reference_entity_test_entity_base_field_info()
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->container->get('entity.definition_update_manager')->applyUpdates();

    // Reference a config entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');
  }

  /**
   * Config entity only revisionable base DER field.
   */
  public function testRevisionableBaseField() {
    // @see dynamic_entity_reference_entity_test_entity_base_field_info()

    // Make this base field revisionable.
    $this->state->set('dynamic_entity_reference_entity_test_revisionable', TRUE);
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->container->get('entity.definition_update_manager')->applyUpdates();

    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Save the entity and update.
    $entity->save();
    $referenced_entity = $this->container->get('entity_type.manager')
      ->getStorage('config_test')
      ->create([
        'id' => 'bar',
        'label' => 'Bar',
        'style' => 'foo',
      ]);
    $referenced_entity->save();
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');
    $entity->save();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->load($entity->id());
    $referenced = $entity->{$this->fieldName}->referencedEntities();
    $this->assertEquals(1, count($referenced));
    $this->assertEquals('bar', $referenced[0]->id());
  }

  /**
   * Content entity and config entity base DER field.
   */
  public function testMixedBaseField() {
    // @see dynamic_entity_reference_entity_test_entity_base_field_info()
    $this->state->set('dynamic_entity_reference_entity_test_entities', [
      $this->entityType,
      'config_test',
      'entity_test_mul',
    ]);
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->container->get('entity.definition_update_manager')->applyUpdates();

    // Reference a config entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Reference a content entity.
    $referenced_entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_mul')
      ->create(['type' => $this->bundle]);
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');
  }

  /**
   * Content entity and config entity revisionable base DER field.
   */
  public function testMixedRevisionableBaseField() {
    // @see dynamic_entity_reference_entity_test_entity_base_field_info()
    // Make this base field revisionable.
    $this->state->set('dynamic_entity_reference_entity_test_revisionable', TRUE);
    $this->state->set('dynamic_entity_reference_entity_test_entities', [
      $this->entityType,
      'config_test',
      'entity_test_mul',
    ]);
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->container->get('entity.definition_update_manager')->applyUpdates();

    // Reference a config entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Save the entity and update to use a content entity.
    $entity->save();
    $referenced_entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_mul')
      ->create(['type' => $this->bundle]);
    $referenced_entity->save();
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');
    $entity->save();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->load($entity->id());
    $referenced = $entity->{$this->fieldName}->referencedEntities();
    $this->assertEquals(1, count($referenced));
    $this->assertEquals($referenced_entity->id(), $referenced[0]->id());
  }

}
