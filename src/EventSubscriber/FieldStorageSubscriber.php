<?php

namespace Drupal\dynamic_entity_reference\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeEvent;
use Drupal\Core\Entity\EntityTypeEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    $events[EntityTypeEvents::UPDATE][] = ['onEntityType', 100];
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
    $this->handleEntityType($definition->getTargetEntityTypeId(), $definition->getName(), $definition);
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
   * @param string|null $field_name
   *   The field name. Optional.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition.
   */
  public function handleEntityType($entity_type_id, $field_name = NULL, FieldStorageDefinitionInterface $field_storage_definition = NULL) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $tables = [];
    // If we know which field is being created / updated check whether it is
    // DER.
    if ($storage instanceof SqlEntityStorageInterface && (!$field_storage_definition || $field_storage_definition->getType() == 'dynamic_entity_reference')) {
      $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      // If a field is given then only work with that.
      $current_definitions = $field_name ? [$field_name => $field_storage_definition] : $storage_definitions;
      // DefaultMapping is buggy and requires all the field definitions.
      $mapping = $storage->getTableMapping($current_definitions + $storage_definitions);
      foreach ($current_definitions as $storage_definition) {
        if ($storage_definition->getType() == 'dynamic_entity_reference') {
          $table = $mapping->getFieldTableName($storage_definition->getName());
          $column = $mapping->getFieldColumnName($storage_definition, 'target_id');
          $tables[$table][] = $column;
        }
      }
      $new = [];
      foreach ($tables as $table => $columns) {
        $new[$table] = $this->intColumnHandler->create($table, $columns);
      }
      foreach (array_filter($new) as $table => $columns) {
        // The trigger created will fill in the right value.
        $this->connection->update($table)->fields([reset($columns) => 0])->execute();
      }
    }
  }

}
