<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests DER update path from 8201 and on.
 *
 * @group dynamic_entity_reference
 */
class DerUpdate8202Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $installProfile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/update_test_8202.php.gz',
    ];
  }

  /**
   * Test that the _int column indexes are properly created.
   *
   * @see \dynamic_entity_reference_update_8202()
   */
  public function testUpdate8202() {
    // The index should not exist initially.
    $schema = \Drupal::database()->schema();
    $index_mapping = [
      // Table => index name.
      'entity_test__field_test' => 'field_test_target_id_int',
      'entity_test_mul__field_test_mul' => 'field_test_mul_target_id_int',
    ];
    foreach ($index_mapping as $table => $index_name) {
      $this->assertFalse($schema->indexExists($table, $index_name));
    }

    // Run updates and verify the indexes have been created.
    $this->runUpdates();
    foreach ($index_mapping as $table => $index_name) {
      $this->assertTrue($schema->indexExists($table, $index_name));
    }
  }

}
