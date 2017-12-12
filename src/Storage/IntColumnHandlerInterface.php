<?php

namespace Drupal\dynamic_entity_reference\Storage;

/**
 * The interface for IntColumnHandler.
 */
interface IntColumnHandlerInterface {

  /**
   * Creates the _int columns and the triggers for them.
   *
   * @param string $table
   *   The non-prefix table to operate on.
   * @param array $columns
   *   The DER target_id columns.
   * @param array $index_columns
   *   Table columns that should be added to the index that is created for the
   *   new _int column.
   *
   * @return array
   *   The list of new target_id_int columns.
   */
  public function create($table, array $columns, array $index_columns = []);

}
