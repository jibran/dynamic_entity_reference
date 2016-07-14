<?php

namespace Drupal\dynamic_entity_reference\Tests\Update;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\system\Tests\Update\UpdatePathTestBase;

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
    // For more information on this db dump see
    // https://www.drupal.org/node/2555027#comment-11307815.
    $this->databaseDumpFiles = [
      __DIR__ . '/der_dump.php.gz',
    ];
  }

  /**
   * Test that target_id is converted to string and target_id_int is created.
   *
   * @see dynamic_entity_reference_update_8001()
   */
  public function testUpdate8001() {
    $this->runUpdates();
    // The db dump contain two entity_test entities referencing one entity_test
    // entity and one entity_test_mul entity.
    $this->assertEqual([1, 1, 1, 1], \Drupal::database()->query('SELECT field_test_target_id_int FROM {entity_test__field_test}')->fetchCol());
    // The db dump contain two entity_test_mul entities referencing one
    // entity_test entity and a entity_test_mul entity.
    $this->assertEqual([1, 1, 1, 1], \Drupal::database()->query('SELECT field_test_mul_target_id_int FROM {entity_test_mul__field_test_mul}')->fetchCol());
    $referenced_entity = EntityTestStringId::create([
      'id' => 'test',
    ]);
    $referenced_entity->save();
    $entity = EntityTestMul::load(3);
    $entity->field_test_mul[] = $referenced_entity;
    $entity->save();
    $this->assertEqual([1, 1, 1, 1, 0], \Drupal::database()->query('SELECT field_test_mul_target_id_int FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());
    $this->assertEqual([1, 1, 1, 1, 'test'], \Drupal::database()->query('SELECT field_test_mul_target_id FROM {entity_test_mul__field_test_mul} ORDER BY entity_id, delta')->fetchCol());
  }

}
