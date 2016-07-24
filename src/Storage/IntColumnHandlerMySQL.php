<?php

namespace Drupal\dynamic_entity_reference\Storage;

/**
 * MySQL implementation of denormalizing into integer columns.
 */
class IntColumnHandlerMySQL extends IntColumnHandler {

  /**
   * {@inheritdoc}
   */
  protected function createBody($column_int, $column) {
    return "NEW.$column_int = IF(NEW.$column REGEXP '^[0-9]+$', CAST(NEW.$column AS UNSIGNED), NULL)";
  }

  /**
   * {@inheritdoc}
   */
  protected function createTrigger($trigger, $op, $prefixed_name, $body) {
    $this->connection->query("CREATE TRIGGER $trigger BEFORE $op ON $prefixed_name FOR EACH ROW SET $body");
  }

}
