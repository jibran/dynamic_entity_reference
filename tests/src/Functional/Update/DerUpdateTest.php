<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional\Update;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests DER update path.
 *
 * @group dynamic_entity_reference
 */
class DerUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $installProfile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    // This dump is created using 8.x-1.x branch of dynamic_entity_reference
    // after installing testing profile and running update_test_8201.php script.
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/der_dump.php.gz',
    ];
  }

  /**
   * Test that target_id is converted to string and target_id_int is created.
   *
   * @see dynamic_entity_reference_update_8201()
   */
  public function testUpdate8201() {
    $connection = \Drupal::database();
    if ($connection->driver() == 'mysql') {
      // This might force an 1071 Specified key was too long; max key length
      // is 767 bytes error if innodb_large_prefix is ON so test it.
      $connection->query('ALTER TABLE {entity_test__field_test} ROW_FORMAT=compact');
    }
    $this->runUpdates();
    // The db dump contain two entity_test entities referencing one entity_test
    // entity and one entity_test_mul entity.
    // Check the basefields value on entity table columns.
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test} ORDER BY id')->fetchCol());
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test} ORDER BY id')->fetchCol());
    // Check the both columns of configurable fields values.
    $this->assertEquals([1, 1, 1, 1], $connection->query('SELECT field_test_target_id FROM {entity_test__field_test} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEquals([1, 1, 1, 1], $connection->query('SELECT field_test_target_id_int FROM {entity_test__field_test} ORDER BY entity_id, delta')->fetchCol());
    // The db dump contain two entity_test entities referencing one entity_test
    // entity and one entity_test_mul entity.
    // Check the basefields values on entity data table columns.
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_mul_property_data} ORDER BY id')->fetchCol());
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_mul_property_data} ORDER BY id')->fetchCol());
    // Check the both columns of configurable fields values.
    $this->assertEquals([1, 1, 1, 1], $connection->query('SELECT field_test_mul_target_id FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEquals([1, 1, 1, 1], $connection->query('SELECT field_test_mul_target_id_int FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());

    // String id entity can be referenced now.
    $referenced_entity = EntityTestStringId::create([
      'id' => 'test',
    ]);
    $referenced_entity->save();
    $entity = EntityTest::load(3);
    $entity->field_test[] = $referenced_entity;
    $entity->save();
    // Check the values in both columns.
    $this->assertEquals([1, 1, 1, 1, 0], $connection->query('SELECT field_test_target_id_int FROM {entity_test__field_test} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEquals([1, 1, 1, 1, 'test'], $connection->query('SELECT field_test_target_id FROM {entity_test__field_test} ORDER BY entity_id, delta')->fetchCol());
    $entity = EntityTestMul::load(3);
    $entity->field_test_mul[] = $referenced_entity;
    $entity->save();
    // Check the values in both columns.
    $this->assertEquals([1, 1, 1, 1, 0], $connection->query('SELECT field_test_mul_target_id_int FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEquals([1, 1, 1, 1, 'test'], $connection->query('SELECT field_test_mul_target_id FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());

    // Create some test entities which link each other.
    $referenced_entity = EntityTest::load(1);
    $referenced_entity_mul = EntityTestMul::load(1);
    // Create test entity without any reference.
    $entity = EntityTest::create();
    $entity->save();
    // Create test data table entity without any reference.
    $entity = EntityTestMul::create();
    $entity->save();
    // Create test entity with all kind of references.
    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity;
    $entity->field_test[] = $referenced_entity_mul;
    $entity->dynamic_references[] = $referenced_entity_mul;
    $entity->save();
    // Create test data table entity with all kind of references.
    $entity = EntityTestMul::create();
    $entity->field_test_mul[] = $referenced_entity;
    $entity->field_test_mul[] = $referenced_entity_mul;
    $entity->dynamic_references[] = $referenced_entity;
    $entity->save();
    // Check the basefields value on entity table columns.
    $this->assertEquals([NULL, 1, 1, NULL, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test} ORDER BY id')->fetchCol());
    $this->assertEquals([NULL, 1, 1, NULL, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test} ORDER BY id')->fetchCol());
    // Check the both columns of configurable fields values.
    $this->assertEquals([1, 1, 1, 1, 0, 1, 1], $connection->query('SELECT field_test_target_id_int FROM {entity_test__field_test} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEquals([1, 1, 1, 1, 'test', 1, 1], $connection->query('SELECT field_test_target_id FROM {entity_test__field_test} ORDER BY entity_id, delta')->fetchCol());
    // Check the basefields values on entity data table columns.
    $this->assertEquals([NULL, 1, 1, NULL, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_mul_property_data} ORDER BY id')->fetchCol());
    $this->assertEquals([NULL, 1, 1, NULL, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_mul_property_data} ORDER BY id')->fetchCol());
    // Check the both columns of configurable fields values.
    $this->assertEquals([1, 1, 1, 1, 0, 1, 1], $connection->query('SELECT field_test_mul_target_id_int FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEquals([1, 1, 1, 1, 'test', 1, 1], $connection->query('SELECT field_test_mul_target_id FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());

    // Even though this was fixed after this update hook, since this one adds
    // the _int column, the index is created at that time.
    // @see \Drupal\Tests\dynamic_entity_reference\Functional\Update\DerUpdate8202
    $this->assertTrue(\Drupal::database()->schema()->indexExists('entity_test', 'dynamic_references__target_id_int'));
    $this->assertTrue(\Drupal::database()->schema()->indexExists('entity_test__field_test', 'field_test_target_id_int'));
    $this->assertTrue(\Drupal::database()->schema()->indexExists('entity_test_mul__field_test_mul', 'field_test_mul_target_id_int'));
  }

}
