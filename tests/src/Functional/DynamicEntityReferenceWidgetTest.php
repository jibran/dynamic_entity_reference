<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests dynamic entity reference field widgets.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceWidgetTest extends BrowserTestBase {

  /**
   * A user with permission to administer content types, node fields, etc.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'dynamic_entity_reference',
    'field_ui',
    'node',
    'config_test',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access content',
    'administer content types',
    'administer node fields',
    'administer node form display',
    'bypass node access',
  ];

  /**
   * Sets up a Drupal site for running functional and integration tests.
   */
  protected function setUp() {
    parent::setUp();

    // Create default content type.
    $this->drupalCreateContentType(['type' => 'reference_content']);
    $this->drupalCreateContentType(['type' => 'referenced_content']);

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser($this->permissions);

    $field_name = Unicode::strtolower($this->randomMachineName());
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'dynamic_entity_reference',
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'node',
        ],
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'reference_content',
      'settings' => [
        'node' => [
          'handler' => 'default',
          'handler_settings' => [
            'target_bundles' => ['referenced_content'],
            'sort' => ['field' => '_none'],
          ],
        ],
      ],
    ])->save();
    $this->fieldName = $field_name;
  }

  /**
   * Tests default autocomplete widget.
   */
  public function testEntityReferenceDefaultWidget() {
    $assert_session = $this->assertSession();
    $field_name = $this->fieldName;
    EntityFormDisplay::load('node.reference_content.default')
      ->setComponent($field_name, [
        'type' => 'dynamic_entity_reference_default',
      ])
      ->save();
    $this->drupalLogin($this->adminUser);
    // Create a node to be referenced.
    $referenced_node = $this->drupalCreateNode(['type' => 'referenced_content']);
    $title = $this->randomMachineName();
    $edit = [
      'title[0][value]' => $title,
      $field_name . '[0][target_id]' => $referenced_node->getTitle() . ' (' . $referenced_node->id() . ')',
    ];
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => 'reference_content']));
    // Only 1 target_type is configured, so this field is not available on the
    // node add/edit page.
    $assert_session->fieldNotExists($field_name . '[0][target_type]');
    $this->submitForm($edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($title);
    $assert_session->responseContains(t('@type %title has been created.', ['@type' => 'reference_content', '%title' => $node->toLink($node->label())->toString()]));
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $title]);
    $reference_node = reset($nodes);
    $this->assertEquals($reference_node->get($field_name)->offsetGet(0)->target_type, $referenced_node->getEntityTypeId());
    $this->assertEquals($reference_node->get($field_name)->offsetGet(0)->target_id, $referenced_node->id());

    // Test with config entity.
    $field_config = FieldConfig::loadByName('node', 'reference_content', $field_name);
    $field_config_settings = $field_config->getSettings();
    $field_config->setSetting('config_test', [
      'handler' => 'default',
      'handler_settings' => [],
    ]);
    $field_config->save();

    $field_storage_config = FieldStorageConfig::loadByName('node', $field_name);
    $field_storage_config_settings = $field_storage_config->getSettings();
    $field_storage_config->setSetting('entity_type_ids', ['node', 'config_test']);
    $field_storage_config->save();

    $referenced_entity = $this->container->get('entity_type.manager')->getStorage('config_test')->create(['label' => $this->randomString(), 'id' => Unicode::strtolower($this->randomMachineName())]);
    $referenced_entity->save();

    $title = $this->randomMachineName();
    $edit = [
      'title[0][value]' => $title,
      $field_name . '[0][target_type]' => $referenced_entity->getEntityTypeId(),
      $field_name . '[0][target_id]' => $referenced_entity->label() . ' (' . $referenced_entity->id() . ')',
    ];
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => 'reference_content']));
    // Multiple target_types are configured, so this field should be available
    // on the node add/edit page.
    $assert_session->fieldExists($field_name . '[0][target_type]');
    $this->submitForm($edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($title);
    $assert_session->responseContains(t('@type %title has been created.', ['@type' => 'reference_content', '%title' => $node->toLink($node->label())->toString()]));
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $title]);
    $reference_node = reset($nodes);
    $this->assertEquals($referenced_entity->getEntityTypeId(), $reference_node->get($field_name)->offsetGet(0)->target_type);
    $this->assertEquals($referenced_entity->id(), $reference_node->get($field_name)->offsetGet(0)->target_id);

    $field_config->setSettings($field_config_settings);
    $field_config->save();
    $field_storage_config->setSettings($field_storage_config_settings);
    $field_storage_config->save();
  }

  /**
   * Tests option button widget.
   */
  public function testEntityReferenceOptionsButtonsWidget() {
    $assert_session = $this->assertSession();
    $field_name = $this->fieldName;
    EntityFormDisplay::load('node.reference_content.default')
      ->setComponent($field_name, [
        'type' => 'dynamic_entity_reference_options_buttons',
      ])
      ->save();
    $this->drupalLogin($this->adminUser);
    // Create a node to be referenced.
    $referenced_node = $this->drupalCreateNode(['type' => 'referenced_content']);
    $title = $this->randomMachineName();
    $edit = [
      'title[0][value]' => $title,
      $field_name => $referenced_node->getEntityTypeId() . '-' . $referenced_node->id(),
    ];
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => 'reference_content']));

    // Only one bundle is configuerd, so optgroup should not be added to
    // the select element.
    $assert_session->elementNotContains('css', '[name=' . $field_name . ']', 'optgroup');
    $this->submitForm($edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($title);
    $assert_session->responseContains(t('@type %title has been created.', ['@type' => 'reference_content', '%title' => $node->toLink($node->label())->toString()]));
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $title]);
    $reference_node = reset($nodes);
    $this->assertEquals($reference_node->get($field_name)->offsetGet(0)->target_type, $referenced_node->getEntityTypeId());
    $this->assertEquals($reference_node->get($field_name)->offsetGet(0)->target_id, $referenced_node->id());
  }

  /**
   * Tests option select widget.
   */
  public function testEntityReferenceOptionsSelectWidget() {
    $assert_session = $this->assertSession();
    $field_name = $this->fieldName;
    EntityFormDisplay::load('node.reference_content.default')
      ->setComponent($field_name, [
        'type' => 'dynamic_entity_reference_options_select',
      ])
      ->save();
    $this->drupalLogin($this->adminUser);
    // Create a node to be referenced.
    $referenced_node = $this->drupalCreateNode(['type' => 'referenced_content']);
    $title = $this->randomMachineName();
    $edit = [
      'title[0][value]' => $title,
      $field_name => $referenced_node->getEntityTypeId() . '-' . $referenced_node->id(),
    ];
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => 'reference_content']));
    $this->submitForm($edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($title);
    $assert_session->responseContains(t('@type %title has been created.', ['@type' => 'reference_content', '%title' => $node->toLink($node->label())->toString()]));
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $title]);
    $reference_node = reset($nodes);
    $this->assertEquals($reference_node->get($field_name)->offsetGet(0)->target_type, $referenced_node->getEntityTypeId());
    $this->assertEquals($reference_node->get($field_name)->offsetGet(0)->target_id, $referenced_node->id());

    $field_config = FieldConfig::loadByName('node', 'reference_content', $this->fieldName);
    $node_setting = $field_config->getSetting('node');
    $field_config->setSetting('node', [
      'handler' => 'default',
      'handler_settings' => [
        'target_bundles' => ['referenced_content', 'reference_content'],
        'sort' => ['field' => '_none'],
      ],
    ]);
    $field_config->save();

    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => 'reference_content']));
    // Multiple target_bundles configured, optgroup should be added to the
    // select element.
    $assert_session->elementContains('css', '[name=' . $field_name . ']', 'optgroup');

    $field_config->setSetting('node', $node_setting);

  }

}
