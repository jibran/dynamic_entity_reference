<?php

namespace Drupal\dynamic_entity_reference\Storage;

use Drupal\Core\Database\Connection;

/**
 * PostgreSQL implementation of denormalizing into integer columns.
 */
class IntColumnHandlerPostgreSQL implements IntColumnHandlerInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * IntColumnHandlerPostgreSQL constructor.
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
  public function create($table, array $columns, array $index_columns = []) {
    $schema = $this->connection->schema();
    if (!IntColumnHandler::allColumnsExist($schema, $table, $columns)) {
      return [];
    }
    // The integer column specification.
    $spec = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
    ];
    $new = [];
    foreach ($columns as $column) {
      $column_int = $column . '_int';
      // Make sure the integer columns exist.
      if (!$schema->fieldExists($table, $column_int)) {
        $this->createTriggerFunction($table, $column, $column_int);
        $this->createTrigger($table, $column, $column_int);

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
        $schema->addField($table, $column_int, $spec);
        $schema->addIndex($table, $column_int, $index_fields, $full_spec);
        $new[] = $column_int;
      }
    }
    return $new;
  }

  /**
   * Creates the actual table function.
   *
   * @param string $table
   *   The name of the table.
   * @param string $column
   *   The name of the target_id column.
   * @param string $column_int
   *   The name of the target_id_int column.
   */
  protected function createTriggerFunction($table, $column, $column_int) {
    $function_name = $this->getFunctionName($table, $column_int);
    $query = "CREATE OR REPLACE FUNCTION $function_name() RETURNS trigger AS $$
      BEGIN
        NEW.$column_int = (CASE WHEN NEW.$column ~ '^[0-9]+$' THEN NEW.$column ELSE NULL END)::integer";
    if (strpos($query, ';') !== FALSE) {
      throw new \InvalidArgumentException('; is not supported in SQL strings. Use only one statement at a time.');
    }
    $this->connection->query("$query; RETURN NEW; END; $$ LANGUAGE plpgsql IMMUTABLE RETURNS NULL ON NULL INPUT", [], ['allow_delimiter_in_query' => TRUE]);
  }

  /**
   * Creates the trigger.
   *
   * @param string $table
   *   The name of the table.
   * @param string $column
   *   The name of the target_id column.
   * @param string $column_int
   *   The name of the target_id_int column.
   */
  protected function createTrigger($table, $column, $column_int) {
    $function_name = $this->getFunctionName($table, $column_int);
    $prefixed_table = $this->getPrefixedTable($table);
    // It is much easier to just drop and recreate than figuring it out whether
    // it exists.
    $this->connection->query("DROP TRIGGER IF EXISTS $column_int ON $prefixed_table");
    $this->connection->query("
      CREATE TRIGGER $column_int
        BEFORE INSERT OR UPDATE
        ON $prefixed_table
        FOR EACH ROW
        EXECUTE PROCEDURE $function_name();
    ");
  }

  /**
   * Returns an appropriate plpgsql function name.
   *
   * @param string $table
   *   The name of the table.
   * @param string $column_int
   *   The name of the target_id_int column.
   *
   * @return string
   *   The plpgsql function name.
   */
  protected function getFunctionName($table, $column_int) {
    return implode('_', [$this->getPrefixedTable($table), $column_int]);
  }

  /**
   * Gets the prefxied table name.
   *
   * @param string $table
   *   The name of the table.
   *
   * @return string
   *   The prefixed table name.
   */
  protected function getPrefixedTable($table) {
    return $this->connection->prefixTables('{' . $table . '}');
  }

}
