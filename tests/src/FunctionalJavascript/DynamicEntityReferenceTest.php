<?php

namespace Drupal\Tests\dynamic_entity_reference\FunctionalJavascript;

use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Ensures that Dynamic Entity References field works correctly.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceTest extends JavascriptTestBase {

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The another user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anotherUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entity_reference',
    'field_ui',
    'dynamic_entity_reference',
    'entity_test',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access administration pages',
    'view test entity',
    'administer entity_test fields',
    'administer entity_test content',
    'access user profiles',
  ];

  /**
   * Sets the test up.
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->anotherUser = $this->drupalCreateUser();
  }

  /**
   * Tests field settings of dynamic entity reference field.
   */
  public function testFieldSettings() {
    $assert_session = $this->assertSession();
    // Add EntityTestBundle for EntityTestWithBundle.
    EntityTestBundle::create([
      'id' => 'test',
      'label' => 'Test label',
      'description' => 'My test description',
    ])->save();
    $this->drupalLogin($this->adminUser);
    // Add a new dynamic entity reference field.
    $this->drupalGet('entity_test/structure/entity_test/fields/add-field');
    $edit = [
      'label' => 'Foobar',
      'field_name' => 'foobar',
      'new_storage_type' => 'dynamic_entity_reference',
    ];
    $this->submitForm($edit, t('Save and continue'), 'field-ui-field-storage-add-form');
    $page = $this->getSession()->getPage();
    $entity_type_ids_select = $assert_session->selectExists('settings[entity_type_ids][]', $page);
    $entity_type_ids_select->selectOption('user');
    $entity_type_ids_select->selectOption('entity_test', TRUE);
    $assert_session->selectExists('cardinality', $page)
      ->selectOption(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $page->uncheckField('settings[exclude_entity_types]');
    $this->submitForm([], t('Save field settings'), 'field-storage-config-edit-form');
    $target_type_select = $assert_session->selectExists('default_value_input[field_foobar][0][target_type]');
    $target_type_select->selectOption('user');
    $target_type_select->selectOption('entity_test');
    $page = $this->getSession()->getPage();
    $page->checkField('settings[entity_test][handler_settings][target_bundles][entity_test]');
    $this->assertJsCondition('(typeof(jQuery)=="undefined" || (0 === jQuery.active && 0 === jQuery(\':animated\').length))', 20000);
    $page->checkField('settings[entity_test][handler_settings][auto_create]');
    $this->submitForm([], t('Save settings'), 'field-config-edit-form');
    $assert_session->pageTextContains('Saved Foobar configuration');
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_foobar');
    $this->assertEmpty($field_storage->getSetting('exclude_entity_types'));
    $this->assertEquals($field_storage->getSetting('entity_type_ids'), ['entity_test' => 'entity_test', 'user' => 'user']);
    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_foobar');
    $settings = $field_config->getSettings();
    $this->assertEquals($settings['entity_test']['handler'], 'default:entity_test');
    $this->assertNotEmpty($settings['entity_test']['handler_settings']);
    $this->assertEquals($settings['entity_test']['handler_settings']['target_bundles'], ['entity_test' => 'entity_test']);
    $this->assertTrue($settings['entity_test']['handler_settings']['auto_create']);
    $this->assertEmpty($settings['entity_test']['handler_settings']['auto_create_bundle']);
  }

}
