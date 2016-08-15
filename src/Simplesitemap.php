<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Simplesitemap class.
 *
 * Main module class.
 */
class Simplesitemap {

  private $configFactory;
  private $config;
  private $db;
  private $entityTypeManager;
  private static $allowed_link_settings = [
    'entity' => ['index', 'priority'],
    'custom' => ['priority']];

  /**
   * Simplesitemap constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactoryInterface
   *   The config factory from the container.
   *
   * @param $database
   *   The database from the container.
   */
  function __construct(
    ConfigFactoryInterface $configFactoryInterface,
    $database,
    EntityTypeManager $entityTypeManager) {

    $this->configFactory = $configFactoryInterface;
    $this->db = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $this->configFactory->get('simple_sitemap.settings');
  }

  /**
   * Gets a specific sitemap configuration from the configuration storage.
   *
   * @param string $key
   *  Configuration key, like 'entity_types'.
   * @return mixed
   *  The requested configuration.
   */
  public function getConfig($key) {
    return $this->config->get($key);
  }

  private function fetchSitemapChunks() {
    return $this->db
      ->query("SELECT * FROM {simple_sitemap}")
      ->fetchAllAssoc('id');
  }

  /**
   * Saves a specific sitemap configuration to db.
   *
   * @param string $key
   *  Configuration key, like 'entity_types'.
   * @param mixed $value
   *  The configuration to be saved.
   *
   * @return $this
   */
  public function saveConfig($key, $value) {
    $this->configFactory->getEditable('simple_sitemap.settings')
      ->set($key, $value)->save();
    // Refresh config object after making changes.
    $this->config = $this->configFactory->get('simple_sitemap.settings');
    return $this;
  }

  /**
   * Enables sitemap support for an entity type. Enabled entity types show
   * sitemap settings on their bundles. If an enabled entity type does not
   * featured bundles (e.g. 'user'), it needs to be set up with
   * setBundleSettings() as well.
   *
   * @param string $entity_type_id
   *  Entity type id like 'node'.
   *
   * @return bool
   *  TRUE if entity type has been enabled, FALSE if it was not.
   */
  public function enableEntityType($entity_type_id) {
    $entity_types = $this->getConfig('entity_types');
    if (empty($entity_types[$entity_type_id])) {
      $entity_types[$entity_type_id] = [];
      $this->saveConfig('entity_types', $entity_types);
    }
    return $this;
  }

  /**
   * Disables sitemap support for an entity type. Disabling support for an
   * entity type deletes its sitemap settings permanently and removes sitemap
   * settings from entity forms.
   *
   * @param string $entity_type_id
   *  Entity type id like 'node'.
   *
   * @return bool
   *  TRUE if entity type has been disabled, FALSE if it was not.
   */
  public function disableEntityType($entity_type_id) {
    $entity_types = $this->getConfig('entity_types');
    if (isset($entity_types[$entity_type_id])) {
      unset($entity_types[$entity_type_id]);
      $this->saveConfig('entity_types', $entity_types);
    }
    return $this;
  }

  /**
   * Sets sitemap settings for a bundle less entity type (e.g. user) or a bundle
   * of an entity type (e.g. page).
   *
   * @param string $entity_type_id
   *  Entity type id like 'node' the bundle belongs to.
   * @param string $bundle_name
   *  Name of the bundle. NULL if entity type has no bundles.
   * @param array $settings
   *  An array of sitemap settings for this bundle/entity type.
   *  Example: ['index' => TRUE, 'priority' => 0.5]
   *
   * @return $this
   */
  public function setBundleSettings($entity_type_id, $bundle_name = NULL, $settings) {
    $bundle_name = is_null($bundle_name) ? $entity_type_id : $bundle_name;
    $entity_types = $this->getConfig('entity_types');
    $this->addLinkSettings('entity', $settings, $entity_types[$entity_type_id][$bundle_name]);
    $this->saveConfig('entity_types', $entity_types);
    return $this;
  }

  public function getEntityInstanceBundleName($entity) {
    return $entity->getEntityTypeId() == 'menu_link_content'
      ? $entity->getMenuName() : $entity->bundle(); // Menu fix.
  }

  public function getBundleEntityTypeId($entity) {
    return $entity->getEntityTypeId() == 'menu'
      ? 'menu_link_content' : $entity->getEntityType()->getBundleOf(); // Menu fix.
  }

  public function setEntityInstanceSettings($entity_type_id, $id, $settings) {
    $entity_types = $this->getConfig('entity_types');
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    $bundle_name = $this->getEntityInstanceBundleName($entity);
    if (isset($entity_types[$entity_type_id][$bundle_name])) {

      // Check if overrides are different from bundle setting before saving.
      $override = FALSE;
      foreach ($settings as $key => $setting) {
        if ($setting != $entity_types[$entity_type_id][$bundle_name][$key]) {
          $override = TRUE;
          break;
        }
      }
      if ($override) { //Save overrides for this entity if something is different.
        $this->addLinkSettings('entity', $settings, $entity_types[$entity_type_id][$bundle_name]['entities'][$id]);
      }
      else { // Else unset override.
        unset($entity_types[$entity_type_id][$bundle_name]['entities'][$id]);
      }
      $this->saveConfig('entity_types', $entity_types);
    }
    return $this;
  }

  public function getBundleSettings($entity_type_id, $bundle_name = NULL) {
    $bundle_name = is_null($bundle_name) ? $entity_type_id : $bundle_name;
    $entity_types = $this->getConfig('entity_types');
    if (isset($entity_types[$entity_type_id][$bundle_name])) {
      $settings = $entity_types[$entity_type_id][$bundle_name];
      unset($settings['entities']);
      return $settings;
    }
    return FALSE;
  }

  public function bundleIsIndexed($entity_type_id, $bundle_name = NULL) {
    $settings = $this->getBundleSettings($entity_type_id, $bundle_name);
    return !empty($settings['index']);
  }

  public function entityTypeIsEnabled($entity_type_id) {
    $entity_types = $this->getConfig('entity_types');
    return isset($entity_types[$entity_type_id]);
  }

  public function getEntityInstanceSettings($entity_type_id, $id) {
    $entity_types = $this->getConfig('entity_types');
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    $bundle_name = $this->getEntityInstanceBundleName($entity);
    if (isset($entity_types[$entity_type_id][$bundle_name]['entities'][$id])) {
      return $entity_types[$entity_type_id][$bundle_name]['entities'][$id];
    }
    else {
      return $this->getBundleSettings($entity_type_id, $bundle_name);
    }
  }

  public function addCustomLink($path, $settings) {
    if (!\Drupal::service('path.validator')->isValid($path))
      return FALSE; // todo: log error
    if ($path[0] != '/')
      return FALSE; // todo: log error
    $custom_links = $this->getConfig('custom');
    foreach($custom_links as $key => $link) {
      if ($link['path'] == $path) {
        $link_key = $key;
        break;
      }
    }
    $link_key = isset($link_key) ? $link_key : count($custom_links);
    $custom_links[$link_key]['path'] = $path;
    $this->addLinkSettings('entity', $settings, $custom_links[$link_key]);
    $this->saveConfig('custom', $custom_links);
    return $this;
  }

  public function getCustomLink($path) {
    $custom_links = $this->getConfig('custom');
    foreach($custom_links as $key => $link) {
      if ($link['path'] == $path) {
        return $custom_links[$key];
      }
    }
    return FALSE;
  }

  public function removeCustomLink($path) {
    $custom_links = $this->getConfig('custom');
    foreach($custom_links as $key => $link) {
      if ($link['path'] == $path) {
        unset($custom_links[$key]);
        $custom_links = array_values($custom_links);
        $this->saveConfig('custom', $custom_links);
      }
    }
    return $this;
  }

  public function removeCustomLinks() {
    $this->saveConfig('custom', []);
  }

  private function addLinkSettings($type, $settings, &$target) {
    foreach($settings as $setting_key => $setting) {
      if (in_array($setting_key, self::$allowed_link_settings[$type])) {
        switch($setting_key) {
          case 'priority':
            if (Form::isValidPriority($setting)) {
              // todo: register error
              continue;
            }
            break;
          //todo: add index check
        }
        $target[$setting_key] = $setting;
      }
    }
  }

  /**
   * Returns the whole sitemap, a requested sitemap chunk,
   * or the sitemap index file.
   *
   * @param int $chunk_id
   *
   * @return string $sitemap
   *  If no sitemap id provided, either a sitemap index is returned, or the
   *  whole sitemap, if the amount of links does not exceed the max links setting.
   *  If a sitemap id is provided, a sitemap chunk is returned.
   */
  public function getSitemap($chunk_id = NULL) {
    $chunks = $this->fetchSitemapChunks();
    if (is_null($chunk_id) || !isset($chunks[$chunk_id])) {

      // Return sitemap index, if there are multiple sitemap chunks.
      if (count($chunks) > 1) {
        return $this->getSitemapIndex($chunks);
      }
      else { // Return sitemap if there is only one chunk.
        if (isset($chunks[1])) {
          return $chunks[1]->sitemap_string;
        }
        return FALSE;
      }
    }
    else { // Return specific sitemap chunk.
      return $chunks[$chunk_id]->sitemap_string;
    }
  }

  /**
   * Generates the sitemap for all languages and saves it to the db.
   *
   * @param string $from
   *  Can be 'form', 'cron', 'drush' or 'nobatch'.
   *  This decides how the batch process is to be run.
   */
  public function generateSitemap($from = 'form') {
    \Drupal::service('simple_sitemap.sitemap_generator')
    ->setGenerateFrom($from)
    ->startGeneration();
  }

  /**
   * Generates and returns the sitemap index as string.
   *
   * @param array $chunks
   *  Sitemap chunks which to generate the index from.
   *
   * @return string
   *  The sitemap index.
   */
  private function getSitemapIndex($chunks) {
    return \Drupal::service('simple_sitemap.sitemap_generator')
      ->generateSitemapIndex($chunks);
  }

  /**
   * Gets a specific sitemap setting.
   *
   * @param string $name
   *  Name of the setting, like 'max_links'.
   *
   * @return mixed
   *  The current setting from db or FALSE if setting does not exist.
   */
  public function getSetting($name) {
    $settings = $this->getConfig('settings');
    return isset($settings[$name]) ? $settings[$name] : FALSE;
  }

  /**
   * Saves a specific sitemap setting to db.
   *
   * @param $name
   *  Setting name, like 'max_links'.
   * @param $setting
   *  The setting to be saved.
   *
   * @return $this
   */
  public function saveSetting($name, $setting) {
    $settings = $this->getConfig('settings');
    $settings[$name] = $setting;
    $this->saveConfig('settings', $settings);
    return $this;
  }

  /**
   * Returns a 'time ago' string of last timestamp generation.
   *
   * @return mixed
   *  Formatted timestamp of last sitemap generation, otherwise FALSE.
   */
  public function getGeneratedAgo() {
    $chunks = $this->fetchSitemapChunks();
    if (isset($chunks[1]->sitemap_created)) {
      return \Drupal::service('date.formatter')
        ->formatInterval(REQUEST_TIME - $chunks[1]->sitemap_created);
    }
    return FALSE;
  }

  /**
   * Returns objects of entity types that can be indexed by the sitemap.
   *
   * @return array
   *  Objects of entity types that can be indexed by the sitemap.
   */
  public function getSitemapEntityTypes() {
    $entity_types = $this->entityTypeManager->getDefinitions();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface || !method_exists($entity_type, 'getBundleEntityType')) {
        unset($entity_types[$entity_type_id]);
      }
    }
    return $entity_types;
  }

  public function entityTypeIsAtomic($entity_type_id) {
    if ($entity_type_id == 'menu_link_content') // Menu fix.
      return FALSE;
    $sitemap_entity_types = $this->getSitemapEntityTypes();
    if (isset($sitemap_entity_types[$entity_type_id])) {
      $entity_type = $sitemap_entity_types[$entity_type_id];
      if (empty($entity_type->getBundleEntityType())) {
        return TRUE;
      }
    }
    return FALSE; //todo: throw exception
  }
}
