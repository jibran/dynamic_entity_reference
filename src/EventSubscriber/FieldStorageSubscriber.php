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
   * Handles an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition.
   */
  public function handleEntityType($entity_type_id, FieldStorageDefinitionInterface $field_storage_definition = NULL) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $der_fields = $this->entityFieldManager->getFieldMapByFieldType('dynamic_entity_reference');
    if ($field_storage_definition) {
      $der_fields[$entity_type_id][$field_storage_definition->getName()] = TRUE;
    }
    $tables = [];
    // If we know which field is being created / updated check whether it is
    // DER.
    if ($storage instanceof SqlEntityStorageInterface && !empty($der_fields[$entity_type_id])) {
      $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      if ($field_storage_definition) {
        $storage_definitions[$field_storage_definition->getName()] = $field_storage_definition;
      }
      $mapping = $storage->getTableMapping($storage_definitions);
      foreach (array_keys($der_fields[$entity_type_id]) as $field_name) {
        try {
          $table = $mapping->getFieldTableName($field_name);
          $column = $mapping->getFieldColumnName($storage_definitions[$field_name], 'target_id');
        }
        catch (SqlContentEntityStorageException $e) {
          // Custom storage? Broken site? No matter what, if there is no table
          // or column, there's little we can do.
          continue;
        }
        $tables[$table][] = $column;
      }
      $new = [];
      foreach ($tables as $table => $columns) {
        $new[$table] = $this->intColumnHandler->create($table, $columns);
      }
      foreach (array_filter($new) as $table => $columns) {
        // reset($columns) is one of the new int columns. The trigger will fill
        // in the right value for it.
        $this->connection->update($table)->fields([reset($columns) => 0])->execute();
      }
    }
  }

}
