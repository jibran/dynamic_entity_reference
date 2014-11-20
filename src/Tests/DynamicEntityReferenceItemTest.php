<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Tests\DynamicEntityReferenceItemTest.
 */

namespace Drupal\dynamic_entity_reference\Tests;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Tests\FieldUnitTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Utility\Unicode;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Tests the new entity API for the dynamic entity reference field type.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceItemTest extends FieldUnitTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('dynamic_entity_reference', 'taxonomy', 'text', 'filter');

  /**
   * The taxonomy vocabulary to test with.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The taxonomy term to test with.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * Sets up the test.
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');

    $this->vocabulary = Vocabulary::create(array(
      'name' => $this->randomMachineName(),
      'vid' => Unicode::strtolower($this->randomMachineName()),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ));
    $this->vocabulary->save();

    $this->term = Term::create(array(
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ));
    $this->term->save();

    // Use the util to create a field.
    FieldStorageConfig::create(array(
      'field_name' => 'field_der',
      'type' => 'dynamic_entity_reference',
      'entity_type' => 'entity_test',
      'cardinality' => 1,
      'settings' => array(
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => array(
          'taxonomy_term' => 'taxonomy_term',
        ),
      ),
    ))->save();

    FieldConfig::create(array(
      'field_name' => 'field_der',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Foo Bar',
      'settings' => array(),
    ))->save();
  }

  /**
   * Tests the dynamic entity reference field type for referencing content entities.
   */
  public function testContentEntityReferenceItem() {
    $tid = $this->term->id();
    $entity_type_id = $this->term->getEntityTypeId();
    // Just being able to create the entity like this verifies a lot of code.
    $entity = EntityTest::create();
    $entity->field_der->target_type = $entity_type_id;
    $entity->field_der->target_id = $tid;
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertTrue($entity->field_der instanceof FieldItemListInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_der[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_der->target_id, $tid);
    $this->assertEqual($entity->field_der->target_type, $entity_type_id);
    $this->assertEqual($entity->field_der->entity->getName(), $this->term->getName());
    $this->assertEqual($entity->field_der->entity->id(), $tid);
    $this->assertEqual($entity->field_der->entity->uuid(), $this->term->uuid());

    // Change the name of the term via the reference.
    $new_name = $this->randomMachineName();
    $entity->field_der->entity->setName($new_name);
    $entity->field_der->entity->save();
    // Verify it is the correct name.
    $term = Term::load($tid);
    $this->assertEqual($term->getName(), $new_name);

    // Make sure the computed term reflects updates to the term id.
    $term2 = Term::create(array(
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ));
    $term2->save();

    $entity->field_der->target_type = $entity_type_id;
    $entity->field_der->target_id = $term2->id();
    $this->assertEqual($entity->field_der->entity->id(), $term2->id());
    $this->assertEqual($entity->field_der->entity->getName(), $term2->getName());

    // Delete terms so we have nothing to reference and try again
    $term->delete();
    $term2->delete();
    $entity = EntityTest::create(array('name' => $this->randomMachineName()));
    $entity->save();

    // Test the generateSampleValue() method.
//    $entity = EntityTest::create();
//    $entity->field_der->generateSampleItems();
//    $entity->field_test_taxonomy_vocabulary->generateSampleItems();
//    $this->entityValidateAndSave($entity);
  }

  /**
   * Tests saving order sequence doesn't matter.
   */
  public function testEntitySaveOrder() {
    // The term entity is unsaved here.
    $term = Term::create(array(
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ));
    $entity = EntityTest::create();
    // Now assign the unsaved term to the field.
    $entity->field_der->entity = $term;
    $entity->name->value = $this->randomMachineName();
    // Now save the term.
    $term->save();
    // And then the entity.
    $entity->save();
    $this->assertEqual($entity->field_der->entity->id(), $term->id());
  }

  /**
   * Tests the dynamic entity reference field type for referencing multiple content entities.
   */
  public function testMultipleEntityReferenceItem() {
    // Allow to reference multiple entities.
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_der');
    $field_storage->set('settings', array(
      'exclude_entity_types' => FALSE,
      'entity_type_ids' => array(
        'taxonomy_term' => 'taxonomy_term',
        'user' => 'user',
      ),
    ))->set('cardinality', FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->save();
    $entity = EntityTest::create();
    $account = User::load(1);
    $entity->field_der[0]->entity = $this->term;
    $entity->field_der[1]->entity = $account;
    $entity->save();
    // Check term reference correctly.
    $this->assertEqual($entity->field_der[0]->target_id, $this->term->id());
    $this->assertEqual($entity->field_der[0]->target_type, $this->term->getEntityTypeId());
    $this->assertEqual($entity->field_der[0]->entity->getName(), $this->term->getName());
    $this->assertEqual($entity->field_der[0]->entity->id(), $this->term->id());
    $this->assertEqual($entity->field_der[0]->entity->uuid(), $this->term->uuid());
    // Check user reference correctly.
    $this->assertEqual($entity->field_der[1]->target_id, $account->id());
    $this->assertEqual($entity->field_der[1]->target_type, $account->getEntityTypeId());
    $this->assertEqual($entity->field_der[1]->entity->id(), $account->id());
    $this->assertEqual($entity->field_der[1]->entity->uuid(), $account->uuid());
  }

}
