<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests for referencing configuration entities with configurable fields.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceConfigEntityTest extends EntityKernelTestBase {

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
  protected $fieldName = 'field_test';

  /**
   * Field storage.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * Field config.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $fieldConfig;

  /**
   * A config entity to reference.
   *
   * @var \Drupal\config_test\ConfigTestInterface
   */
  protected $configTestReference;

  /**
   * A content entity for referencing.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $contentEntityReference;

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
    $this->installEntitySchema('entity_test_string_id');

    $this->configTestReference = $this->container->get('entity_type.manager')
      ->getStorage('config_test')
      ->create([
        'id' => 'foo',
        'label' => 'Foo',
        'style' => 'bar',
      ]);
    $this->configTestReference->save();

    $this->contentEntityReference = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_rev')
      ->create(['type' => $this->bundle]);
    $this->contentEntityReference->save();
  }

  /**
   * Helper method to setup field and field storages.
   */
  protected function setUpField() {
    // Create a field.
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'dynamic_entity_reference',
      'entity_type' => $this->entityType,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => $this->referencedEntityTypes,
      ],
    ]);
    $this->fieldStorage->save();

    $settings = [];
    foreach ($this->referencedEntityTypes as $entity_type_id) {
      $settings[$entity_type_id] = [
        'handler' => "default:$entity_type_id",
        'handler_settings' => [],
      ];
    }
    $this->fieldConfig = FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'label' => 'Field test',
      'settings' => $settings,
    ]);
    $this->fieldConfig->save();
  }

  /**
   * Config entity only configurable DER field.
   */
  public function testConfigurableField() {
    $this->setUpField();

    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 0, 'Validation passes.');
  }

  /**
   * Content entity (int ID) and config entity configurable DER field.
   */
  public function testMixedConfigurableField() {
    $this->referencedEntityTypes[] = 'entity_test_rev';
    $this->setUpField();

    // Check config entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Check content entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->contentEntityReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->contentEntityReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');
  }

  /**
   * String-ID content entity and config entity.
   */
  public function testMixedConfigurableFieldStringId() {
    $this->referencedEntityTypes[] = 'entity_test_string_id';
    $this->setUpField();

    // Check config entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Check content entity (string ID).
    $referenced_entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_string_id')
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
   * Mixed content entity IDs (string and int) and config entity.
   */
  public function testMixedConfigurableFieldMixedIds() {
    $this->referencedEntityTypes[] = 'entity_test_rev';
    $this->referencedEntityTypes[] = 'entity_test_string_id';
    $this->setUpField();

    // Check config entity.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->configTestReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->configTestReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Check content entity (string ID).
    $referenced_entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_string_id')
      ->create(['type' => $this->bundle]);
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');

    // Check content entity (int ID).
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $this->contentEntityReference->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $this->contentEntityReference->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEmpty($violations->count(), 'Validation passes.');
  }

}
