<?php

namespace Drupal\dynamic_entity_reference\Storage;

use Drupal\Core\Database\Connection;

/**
 * Per database implementation of denormalizing into integer columns.
 */
abstract class IntColumnHandler implements IntColumnHandlerInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * IntColumnHandler constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function create($table, array $columns) {
    $schema = $this->connection->schema();
    // The integer column specification.
    $spec = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
    ];
    // Before MySQL 5.7.2, there cannot be multiple triggers for a given table
    // that have the same trigger event and action time so set all involved
    // columns in one go. See
    // https://dev.mysql.com/doc/refman/5.7/en/trigger-syntax.html for more.
    // In SQLite, it's cheaper to run one query instead on per column.
    $body = [];
    $new = [];
    foreach ($columns as $column) {
      $column_int = $column . '_int';
      // Make sure the integer columns exist.
      if (!$schema->fieldExists($table, $column_int)) {
        $new[] = $column_int;
        $schema->addField($table, $column_int, $spec);
      }
      // This is the heart of this function: before an UPDATE/INSERT, set the
      // value of the integer column to the integer value of the string column.
      $body[] = $this->createBody($column_int, $column);
    }
    if ($new) {
      $body = implode(', ', $body);
      $prefixed_name = $this->connection->prefixTables('{' . $table . '}');
      foreach (['update', 'insert'] as $op) {
        $trigger = $prefixed_name . '_der_' . $op;
        if (strlen($trigger) > 64) {
          $trigger = substr($trigger, 0, 56) . substr(hash('sha256', $trigger), 0, 8);
        }
        $this->connection->query("DROP TRIGGER IF EXISTS $trigger");
        if ($body) {
          $this->createTrigger($trigger, $op, $prefixed_name, $body);
        }
      }
    }
    return $new;
  }

  /**
   * Create the body of the trigger.
   *
   * Create a part of the statement to set the value of the integer column to
   * the integer value of the string column.
   *
   * @param string $column_int
   *   The name of the target_id_int column.
   * @param string $column
   *   The name of the target_id column.
   */
  abstract protected function createBody($column_int, $column);

  /**
   * Actually create the trigger.
   *
   * @param string $trigger
   *   The name of the trigger.
   * @param string $op
   *   Either UPDATE or INSSERT.
   * @param string $prefixed_name
   *   The already prefixed table table.
   * @param string $body
   *   The body of the trigger.
   */
  abstract protected function createTrigger($trigger, $op, $prefixed_name, $body);

  /**
   * Removes the trigger.
   *
   * @param string $table
   *   Name of the table.
   * @param string $column
   *   Name of the column.
   *
   * @TODO not sure whether we want to bother with deleting.
   */
  public function delete($table, $column) {

  }

}
