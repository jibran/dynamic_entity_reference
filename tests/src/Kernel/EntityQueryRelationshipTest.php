<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests dynamic entity reference relationship data.
 *
 * @group dynamic_entity_reference
 */
class EntityQueryRelationshipTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   *
   * @var string[]
   */
  protected static $modules = ['dynamic_entity_reference'];

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
  protected $referencedEntityTypes;

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
   * The results returned by EntityQuery.
   *
   * @var array
   */
  protected $queryResults;

  /**
   * The entity_test entities used by the test.
   *
   * @var array
   */
  protected $entities = [];

  /**
   * Tests entity query for DER for entities with integer IDs.
   */
  public function testEntityQuery() {
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('entity_test_rev');
    $this->referencedEntityTypes = ['entity_test_mul', 'entity_test_rev'];
    $this->setupDerField();

    // Create some test entities which link each other.
    $referenced_entity_test_mul = EntityTestMul::create(['name' => 'Foobar']);
    $referenced_entity_test_mul->save();
    $referenced_entity_test_mul_2 = EntityTestMul::create(['name' => 'Barfoo']);
    $referenced_entity_test_mul_2->save();
    $referenced_entity_test_rev = EntityTestRev::create(['name' => 'Foobar']);
    $referenced_entity_test_rev->save();
    $referenced_entity_test_rev_2 = EntityTestRev::create(['name' => 'Barfoo']);
    $referenced_entity_test_rev_2->save();

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity_test_mul;
    $entity->field_test[] = $referenced_entity_test_mul_2;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity_test_mul->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_test_mul_2->id());
    $this->assertEquals($entity->field_test[0]->entity->getEntityTypeId(), $referenced_entity_test_mul->getEntityTypeId());
    $this->assertEquals($entity->field_test[1]->entity->getEntityTypeId(), $referenced_entity_test_mul_2->getEntityTypeId());
    $this->entities[] = $entity;

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity_test_rev;
    $entity->field_test[] = $referenced_entity_test_rev_2;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity_test_rev->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_test_rev_2->id());
    $this->assertEquals($entity->field_test[0]->entity->getEntityTypeId(), $referenced_entity_test_rev->getEntityTypeId());
    $this->assertEquals($entity->field_test[1]->entity->getEntityTypeId(), $referenced_entity_test_rev_2->getEntityTypeId());
    $this->entities[] = $entity;
    // This returns the 0th entity as that's only one pointing to the 0th
    // account.
    $query = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition("field_test.0.entity:entity_test_mul.name", 'Foobar')
      ->condition("field_test.1.entity:entity_test_mul.name", 'Barfoo');
    $this->queryResults = $query->execute();
    $this->assertEquals([1 => 1], $this->queryResults);
    $this->assertJoinColumn($query, 'field_test', 'entity_test_mul', TRUE);
    // This returns the 0th entity as that's only one pointing to the 0th
    // account.
    $query = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition("field_test.0.entity:entity_test_rev.name", 'Foobar')
      ->condition("field_test.1.entity:entity_test_rev.name", 'Barfoo');
    $this->queryResults = $query->execute();
    $this->assertEquals([2 => 2], $this->queryResults);
    $this->assertJoinColumn($query, 'field_test', 'entity_test_rev', TRUE);
  }

  /**
   * Tests entity query for DER for entities with string IDs.
   */
  public function testEntityQueryString() {
    $this->installEntitySchema('entity_test_string_id');
    $this->referencedEntityTypes = ['entity_test_string_id'];
    $this->setupDerField();

    // Create some test entities which link each other.
    $referenced_entity_1 = EntityTestStringId::create([
      'name' => 'Foobar',
      'id' => mb_strtolower($this->randomMachineName()),
    ]);
    $referenced_entity_1->save();
    $referenced_entity_2 = EntityTestStringId::create([
      'name' => 'Barfoo',
      'id' => mb_strtolower($this->randomMachineName()),
    ]);
    $referenced_entity_2->save();

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity_1;
    $entity->field_test[] = $referenced_entity_2;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity_1->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_2->id());
    $this->entities[] = $entity;

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity_1;
    $entity->field_test[] = $referenced_entity_2;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity_1->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_2->id());
    $this->entities[] = $entity;
    // This returns the 0th entity as that's only one pointing to the 0th
    // account.
    $query = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition("field_test.0.entity:entity_test_string_id.name", 'Foobar')
      ->condition("field_test.1.entity:entity_test_string_id.name", 'Barfoo');
    $this->queryResults = $query->execute();
    $this->assertEquals([1 => 1, 2 => 2], $this->queryResults);
    $this->assertJoinColumn($query, 'field_test', 'entity_test_string_id', FALSE);
  }

  /**
   * Helper method to setup the reference field to be tested.
   */
  protected function setupDerField() {
    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'dynamic_entity_reference',
      'entity_type' => $this->entityType,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => $this->referencedEntityTypes,
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
   * Helper method to check which column was joined on.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The executed query.
   * @param string $field_name
   *   The field name being used in the join.
   * @param string $target_type
   *   The target entity type.
   * @param bool $integer_column
   *   TRUE if the expected join column is the `_target_id_int`, FALSE
   *   otherwise.
   */
  protected function assertJoinColumn(QueryInterface $query, $field_name, $target_type, $integer_column = TRUE) {
    // @todo Is there a better way than reflection?
    $reflection = new \ReflectionObject($query);
    $property = $reflection->getProperty('sqlQuery');
    $property->setAccessible(TRUE);
    $sql = (string) $property->getValue($query);
    if ($integer_column) {
      $this->assertTrue(strpos($sql, "[{$field_name}_target_id_int] AND ") !== FALSE, 'Query joined on target_id_int column.');
    }
    else {
      $this->assertTrue(strpos($sql, "[{$field_name}_target_id] AND ") !== FALSE, 'Query joined on target_id column.');
    }
    $this->assertTrue(strpos($sql, "[{$field_name}_target_type] = '$target_type'") !== FALSE, 'Query joined on target_type column.');
  }

}
