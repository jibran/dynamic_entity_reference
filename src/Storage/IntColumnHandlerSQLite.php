<?php

namespace Drupal\dynamic_entity_reference\Storage;

/**
 * SQLite implementation of denormalizing into integer columns.
 */
class IntColumnHandlerSQLite extends IntColumnHandler {

  /**
   * {@inheritdoc}
   */
  protected function createBody($column_int, $column) {
    return "$column_int = CAST($column AS INTEGER)";
  }

  /**
   * {@inheritdoc}
   */
  protected function createTrigger($trigger, $op, $prefixed_name, $body) {
    $parts = explode('.', $prefixed_name);
    // Simpletest for example prefixes with a database name but SQLite does
    // not support referencing databases in the body of the trigger (even if it
    // is the same database the triggering table is in).
    $table_name = array_pop($parts);
    $query = "
        CREATE TRIGGER $trigger AFTER $op ON $prefixed_name
        FOR EACH ROW
        BEGIN
          UPDATE $table_name SET $body WHERE ROWID=NEW.ROWID";
    // SQLite requires a ; in the query which requires bypassing Drupal's built
    // in single statement only protection. Although this method is not
    // supposed to be called by user submitted data.
    if (strpos($query, ';') !== FALSE) {
      throw new \InvalidArgumentException('; is not supported in SQL strings. Use only one statement at a time.');
    }
    $this->connection->query("$query; END", [], ['allow_delimiter_in_query' => TRUE]);
  }

}
