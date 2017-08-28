<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional\Update;

use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the revisionable DER fields update path.
 *
 * @group dynamic_entity_reference
 */
class DerRevUpdateTest extends UpdatePathTestBase {

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
      __DIR__ . '/../../../fixtures/update/der_rev_dump.php.gz',
    ];
  }

  /**
   * Test that target_id is converted to string and target_id_int is created.
   *
   * @see dynamic_entity_reference_update_8201()
   */
  public function testUpdate8201() {
    $connection = \Drupal::database();
    $this->runUpdates();
    // The db dump contain two entity_test_rev entities referencing one
    // entity_test_rev entity and one entity_test_mulrev entity.
    // Check the basefields value on entity table columns.
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT der__target_id FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT der__target_id_int FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    // The db dump contain two entity_test_mulrev entities referencing one
    // entity_test_rev entity and one entity_test_mulrev entity.
    // Check the basefields value on entity data table columns.
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT der__target_id FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1], $connection->query('SELECT der__target_id_int FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());

    // Check the basefields value on entity revision table columns.
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1], $connection->query('SELECT der__target_id FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1], $connection->query('SELECT der__target_id_int FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    // Check the basefields value on entity revision data table columns.
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1], $connection->query('SELECT der__target_id FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1], $connection->query('SELECT der__target_id_int FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());

    // String id entity can be referenced now.
    $referenced_entity = EntityTestStringId::create([
      'id' => 'test',
    ]);
    $referenced_entity->save();
    $entity = EntityTestRev::load(3);
    $entity->dynamic_references[0] = $entity->der[0] = $referenced_entity;
    $entity->setNewRevision(TRUE);
    $entity->save();
    $entity = EntityTestMulRev::load(3);
    $entity->dynamic_references[0] = $entity->der[0] = $referenced_entity;
    $entity->setNewRevision(TRUE);
    $entity->save();
    // Check the basefields value on entity table columns.
    $this->assertEquals([NULL, 1, 'test'], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 0], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, 1, 'test'], $connection->query('SELECT der__target_id FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 0], $connection->query('SELECT der__target_id_int FROM {entity_test_rev} ORDER BY id, revision_id')->fetchCol());
    // The db dump contain two entity_test_mulrev entities referencing one
    // entity_test_rev entity and one entity_test_mulrev entity.
    // Check the basefields value on entity data table columns.
    $this->assertEquals([NULL, 1, 'test'], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 0], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, 1, 'test'], $connection->query('SELECT der__target_id FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 0], $connection->query('SELECT der__target_id_int FROM {entity_test_mulrev_property_data} ORDER BY id, revision_id')->fetchCol());

    // Check the basefields value on entity revision table columns.
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1, 'test'], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1, 0], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1, 'test'], $connection->query('SELECT der__target_id FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1, 0], $connection->query('SELECT der__target_id_int FROM {entity_test_rev_revision} ORDER BY id, revision_id')->fetchCol());
    // Check the basefields value on entity revision data table columns.
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1, 'test'], $connection->query('SELECT dynamic_references__target_id FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, 1, 1, 1, 1, 1, 1, 1, 1, 0], $connection->query('SELECT dynamic_references__target_id_int FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
    // Check the both columns of the other basefield.
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1, 'test'], $connection->query('SELECT der__target_id FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
    $this->assertEquals([NULL, NULL, NULL, 1, 1, NULL, NULL, 1, 1, 0], $connection->query('SELECT der__target_id_int FROM {entity_test_mulrev_property_revision} ORDER BY id, revision_id')->fetchCol());
  }

}
