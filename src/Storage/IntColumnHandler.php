<?php

namespace Drupal\dynamic_entity_reference\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;

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
   * Checks whether all columns exist.
   *
   * @param \Drupal\Core\Database\Schema $schema
   *   The database Schema object for this connection.
   * @param string $table
   *   The name of the table in drupal (no prefixing).
   * @param string[] $columns
   *   The names of the columns.
   *
   * @return bool
   *   TRUE if all the given columns exists, otherwise FALSE.
   */
  public static function allColumnsExist(Schema $schema, $table, array $columns) {
    foreach ($columns as $column) {
      // When a new module adds more than one new basefields in
      // hook_entity_base_field_info() then the entity system will report those
      // but they won't exist yet in the database. It's enough to fire when
      // called for the last one.
      if (!$schema->fieldExists($table, $column)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function create($table, array $columns, array $index_columns = []) {
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
    if (!static::allColumnsExist($schema, $table, $columns)) {
      return [];
    }
    foreach ($columns as $column) {
      $column_int = $column . '_int';
      // Make sure the integer columns exist.
      if (!$schema->fieldExists($table, $column_int)) {
        $index_fields = [$column_int];
        $full_spec = [
          'fields' => [
            $column_int => $spec,
          ],
        ];

        if (!empty($index_columns)) {
          $full_spec['fields'] = array_merge($full_spec['fields'], $index_columns);
          $index_fields = array_merge($index_fields, array_keys($index_columns));
        }

        $new[] = $column_int;
        $schema->addField($table, $column_int, $spec);
        $schema->addIndex($table, $column_int, $index_fields, $full_spec);
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
   * Creates the body of the trigger.
   *
   * Creates a part of the statement to set the value of the integer column to
   * the integer value of the string column.
   *
   * @param string $column_int
   *   The name of the target_id_int column.
   * @param string $column
   *   The name of the target_id column.
   */
  abstract protected function createBody($column_int, $column);

  /**
   * Actually creates the trigger.
   *
   * @param string $trigger
   *   The name of the trigger.
   * @param string $op
   *   Either UPDATE or INSERT.
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
