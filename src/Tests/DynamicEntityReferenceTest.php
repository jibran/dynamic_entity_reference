<?php

/**
 * @file
 * Contains \Drupal\dynamic_entity_reference\Tests\DynamicEntityReferenceTest.
 */

namespace Drupal\dynamic_entity_reference\Tests;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simpletest\WebTestBase;
use Symfony\Component\CssSelector\CssSelector;

/**
 * Ensures that Dynamic Entity References field works correctly.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceTest extends WebTestBase {

  /**
   * Profile to use.
   */
  protected $profile = 'testing';

  /**
   * Admin user
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'entity_reference',
    'field_ui',
    'dynamic_entity_reference',
    'entity_test',
  );

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = array(
    'access administration pages',
    'view test entity',
    'administer entity_test fields',
    'administer entity_test content',
  );

  /**
   * Sets the test up.
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser($this->permissions);
  }

  /**
   * Tests field settings of dynamic entity reference field.
   */
  public function testFieldSettings() {
    $this->drupalLogin($this->adminUser);
    // Add a new dynamic entity reference field.
    $this->drupalGet('entity_test/structure/entity_test/fields');
    $edit = array(
      'fields[_add_new_field][label]' => 'Foobar',
      'fields[_add_new_field][field_name]' => 'foobar',
      'fields[_add_new_field][type]' => 'dynamic_entity_reference',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, array(
      'field[cardinality]' => '-1',
      'field[settings][entity_type_ids][]' => 'user',
    ), t('Save field settings'));
    $this->assertFieldByName('default_value_input[field_foobar][0][target_type]');
    $this->assertFieldByXPath(CssSelector::toXPath('select[name="default_value_input[field_foobar][0][target_type]"] > option[value=entity_test]'), 'entity_test');
    $this->assertNoFieldByXPath(CssSelector::toXPath('select[name="default_value_input[field_foobar][0][target_type]"] > option[value=user]'), 'user');
    $this->drupalPostForm(NULL, array(), t('Save settings'));
    $this->assertRaw(t('Saved %name configuration', array('%name' => 'Foobar')));
    $excluded_entity_type_ids = FieldStorageConfig::loadByName('entity_test', 'field_foobar')
      ->getSetting('entity_type_ids');
    $this->assertNotNull($excluded_entity_type_ids);
    $this->assertIdentical(array_keys($excluded_entity_type_ids), array('user'));
    // Check the include entity settings.
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.field_foobar/storage');
    $this->drupalPostForm(NULL, array(
      'field[cardinality]' => '-1',
      'field[settings][exclude_entity_types]' => FALSE,
      'field[settings][entity_type_ids][]' => 'user',
    ), t('Save field settings'));
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.field_foobar');
    $this->assertFieldByName('default_value_input[field_foobar][0][target_type]');
    $this->assertFieldByXPath(CssSelector::toXPath('select[name="default_value_input[field_foobar][0][target_type]"] > option[value=user]'), 'user');
    $this->assertNoFieldByXPath(CssSelector::toXPath('select[name="default_value_input[field_foobar][0][target_type]"] > option[value=entity_test]'), 'entity_test');
    $this->drupalPostForm(NULL, array(), t('Save settings'));
    $this->assertRaw(t('Saved %name configuration', array('%name' => 'Foobar')));
    $excluded_entity_type_ids = FieldStorageConfig::loadByName('entity_test', 'field_foobar')
      ->getSetting('entity_type_ids');
    $this->assertNotNull($excluded_entity_type_ids);
    $this->assertIdentical(array_keys($excluded_entity_type_ids), array('user'));
  }

  /**
   * Tests adding and editing values using dynamic entity reference.
   */
  public function testDynamicEntityReference() {
    $this->drupalLogin($this->adminUser);
    // Add a new dynamic entity reference field.
    $this->drupalGet('entity_test/structure/entity_test/fields');
    $edit = array(
      'fields[_add_new_field][label]' => 'Foobar',
      'fields[_add_new_field][field_name]' => 'foobar',
      'fields[_add_new_field][type]' => 'dynamic_entity_reference',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, array(
      'field[cardinality]' => '-1',
    ), t('Save field settings'));

    $this->drupalPostForm(NULL, array(), t('Save settings'));
    $this->assertRaw(t('Saved %name configuration', array('%name' => 'Foobar')));

    // Create some items to reference.
    $item1 = entity_create('entity_test', array(
      'name' => 'item1',
    ));
    $item1->save();
    $item2 = entity_create('entity_test', array(
      'name' => 'item2',
    ));
    $item2->save();

    // Test the new entity commenting inherits default.
    $this->drupalGet('entity_test/add');
    $this->assertField('field_foobar[0][target_id]', 'Found foobar field target id');
    $this->assertField('field_foobar[0][target_type]', 'Found foobar field target type');

    // Add some extra dynamic entity reference fields.
    $this->drupalPostAjaxForm(NULL, array(), array('field_foobar_add_more' => t('Add another item')), 'system/ajax', array(), array(), 'entity-test-entity-test-form');
    $this->drupalPostAjaxForm(NULL, array(), array('field_foobar_add_more' => t('Add another item')), 'system/ajax', array(), array(), 'entity-test-entity-test-form');

    $edit = array(
      'field_foobar[0][target_id]' => $this->adminUser->label() . ' (' . $this->adminUser->id() . ')',
      'field_foobar[0][target_type]' => 'user',
      'field_foobar[1][target_id]' => 'item1 (' . $item1->id() . ')',
      'field_foobar[1][target_type]' => 'entity_test',
      'field_foobar[2][target_id]' => 'item2 (' . $item2->id() . ')',
      'field_foobar[2][target_type]' => 'entity_test',
      'name' => 'Barfoo',
      'user_id' => $this->adminUser->id(),
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $entities = entity_load_multiple_by_properties('entity_test', array(
      'name' => 'Barfoo',
    ));
    $this->assertEqual(1, count($entities), 'Entity was saved');
    $entity = reset($entities);
    $this->drupalGet('entity_test/' . $entity->id());
    $this->assertText('Barfoo');
    $this->assertText($this->adminUser->label());
    $this->assertText('item1');
    $this->assertText('item2');

    $this->assertEqual(count($entity->field_foobar), 3, 'Three items in field');
    $this->assertEqual($entity->field_foobar[0]->entity->label(), $this->adminUser->label());
    $this->assertEqual($entity->field_foobar[1]->entity->label(), 'item1');
    $this->assertEqual($entity->field_foobar[2]->entity->label(), 'item2');

    $this->drupalGet('entity_test/manage/' . $entity->id());
    $edit = array(
      'name' => 'Bazbar',
      // Remove one child.
      'field_foobar[2][target_id]' => '',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('entity_test/' . $entity->id());
    $this->assertText('Bazbar');
    // Reload entity.
    \Drupal::entityManager()->getStorage('entity_test')->resetCache(array($entity->id()));
    $entity = entity_load('entity_test', $entity->id());
    $this->assertEqual(count($entity->field_foobar), 2, 'Two values in field');
  }

}
