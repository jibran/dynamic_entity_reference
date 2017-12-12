<?php

namespace Drupal\dynamic_entity_reference\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeEvent;
use Drupal\Core\Entity\EntityTypeEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageException;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionEvent;
use Drupal\Core\Field\FieldStorageDefinitionEvents;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\dynamic_entity_reference\Storage\IntColumnHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hands off field storage events to the integer column handler.
 */
class FieldStorageSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database specific handler creating the _int column.
   *
   * @var \Drupal\dynamic_entity_reference\Storage\IntColumnHandler
   */
  protected $intColumnHandler;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * FieldStorageSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\dynamic_entity_reference\Storage\IntColumnHandlerInterface $int_column_handler
   *   The integer column handler.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, IntColumnHandlerInterface $int_column_handler, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->intColumnHandler = $int_column_handler;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // When enabling a module implementing an entity type,
    // EntityTypeEvents::CREATE fires and FieldStorageDefinitionEvents::CREATE
    // does not. On the other hand, when adding a field
    // to an existing entity type, EntityTypeEvents::UPDATE does not fire but
    // FieldStorageDefinitionEvents::CREATE does. This is true for saving a
    // FieldStorageConfig object or enabling a module implementing
    // hook_entity_base_field_info().
    $events[FieldStorageDefinitionEvents::CREATE][] = ['onFieldStorage', 100];
    $events[EntityTypeEvents::CREATE][] = ['onEntityType', 100];
    return $events;
  }

  /**
   * Handle a field storage event.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionEvent $event
   *   The event to process.
   */
  public function onFieldStorage(FieldStorageDefinitionEvent $event) {
    $definition = $event->getFieldStorageDefinition();
    $this->handleEntityType($definition->getTargetEntityTypeId(), $definition);
  }

  /**
   * Handle an entity type event.
   *
   * @param \Drupal\Core\Entity\EntityTypeEvent $event
   *   The event to process.
   */
  public function onEntityType(EntityTypeEvent $event) {
    $this->handleEntityType($event->getEntityType()->id());
  }

  /**
   * Adds integer columns and relevant triggers for an entity type.
   *
   * Every dyanmic_entity_reference field belonging to an entity type will get
   * an integer column pair and a trigger which calculates the integer value if
   * the target_id looks like a number. This makes it possible to store a
   * string for entities which have string IDs and yet JOIN and ORDER on
   * integers when that's desired.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition. It is only necessary to pass this if this
   *   a FieldStorageConfig object during presave and as such the definition is
   *   not yet available to the entity field manager.
   */
  public function handleEntityType($entity_type_id, FieldStorageDefinitionInterface $field_storage_definition = NULL) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $der_fields = $this->entityFieldManager->getFieldMapByFieldType('dynamic_entity_reference');
    if ($field_storage_definition && ($field_storage_definition->getType() === 'dynamic_entity_reference')) {
      $der_fields[$entity_type_id][$field_storage_definition->getName()] = TRUE;
    }
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $tables = [];
    $index_columns = [];
    // If we know which field is being created / updated check whether it is
    // DER.
    if ($storage instanceof SqlEntityStorageInterface && !empty($der_fields[$entity_type_id])) {
      $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      if ($field_storage_definition) {
        $storage_definitions[$field_storage_definition->getName()] = $field_storage_definition;
      }
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
      $mapping = $storage->getTableMapping($storage_definitions);
      foreach (array_keys($der_fields[$entity_type_id]) as $field_name) {
        try {
          $table = $mapping->getFieldTableName($field_name);
          $column = $mapping->getFieldColumnName($storage_definitions[$field_name], 'target_id');
          $index_column = $mapping->getFieldColumnName($storage_definitions[$field_name], 'target_type');
        }
        catch (SqlContentEntityStorageException $e) {
          // Custom storage? Broken site? No matter what, if there is no table
          // or column, there's little we can do.
          continue;
        }
        $tables[$table][] = $column;

        $schema_info = $storage_definitions[$field_name]->getSchema();
        $index_columns[$table] = [
          $index_column => $schema_info['columns']['target_type'],
        ];
        if ($entity_type->isRevisionable() && ($storage_definitions[$field_name]->isRevisionable())) {
          try {
            if ($mapping->requiresDedicatedTableStorage($storage_definitions[$field_name])) {
              $tables[$mapping->getDedicatedRevisionTableName($storage_definitions[$field_name])][] = $column;
              $index_columns[$mapping->getDedicatedRevisionTableName($storage_definitions[$field_name])] = [
                $index_column => $schema_info['columns']['target_type'],
              ];
            }
            elseif ($mapping->allowsSharedTableStorage($storage_definitions[$field_name])) {
              $revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
              $tables[$revision_table][] = $column;
              $tables[$revision_table] = array_unique($tables[$revision_table]);
              $index_columns[$revision_table] = [
                $index_column => $schema_info['columns']['target_type'],
              ];
            }
          }
          catch (SqlContentEntityStorageException $e) {
            // Nothing to do if the revision table doesn't exist.
          }
        }
      }
      $new = [];
      foreach ($tables as $table => $columns) {
        $new[$table] = $this->intColumnHandler->create($table, $columns, $index_columns[$table]);
      }
      foreach (array_filter($new) as $table => $columns) {
        // reset($columns) is one of the new int columns. The trigger will fill
        // in the right value for it.
        $this->connection->update($table)->fields([reset($columns) => 0])->execute();
      }
    }
  }

}
