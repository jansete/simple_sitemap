<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\SitemapGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityUrlGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "entity",
 *   title = @Translation("Entity URL generator"),
 *   description = @Translation("Generates URLs for entity bundles and bundle overrides."),
 *   weight = 10,
 *   settings = {
 *     "instantiate_for_each_data_set" = true,
 *   },
 * )
 */
class EntityUrlGenerator extends UrlGeneratorBase {

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager
   */
  protected $urlGeneratorManager;

  /**
   * EntityUrlGenerator constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param Simplesitemap $generator
   * @param SitemapGenerator $sitemap_generator
   * @param LanguageManagerInterface $language_manager
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param Logger $logger
   * @param EntityHelper $entityHelper
   * @param ModuleHandlerInterface $module_handler
   * @param UrlGeneratorManager $url_generator_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    SitemapGenerator $sitemap_generator,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper,
    ModuleHandlerInterface $module_handler,
    UrlGeneratorManager $url_generator_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $generator,
      $sitemap_generator,
      $language_manager,
      $entity_type_manager,
      $logger,
      $entityHelper,
      $module_handler
    );
    $this->urlGeneratorManager = $url_generator_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.sitemap_generator'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('module_handler'),
      $container->get('plugin.manager.simple_sitemap.url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSets($context) {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();
    $bundle_settings = $this->generator->getBundleSettings($context);

    $this->moduleHandler->alter('simple_sitemap_bundle_settings', $bundle_settings, $context);

    if (!empty($bundle_settings)) {
      foreach ($bundle_settings as $entity_type_name => $bundles) {
        if (isset($sitemap_entity_types[$entity_type_name])) {

          // Skip this entity type if another plugin is written to override its generation.
          foreach ($this->urlGeneratorManager->getDefinitions() as $plugin) {
            if ($plugin['enabled'] && !empty($plugin['settings']['overrides_entity_type'])
              && $plugin['settings']['overrides_entity_type'] === $entity_type_name) {
              continue 2;
            }
          }

          foreach ($bundles as $bundle_name => $bundle_settings) {
            if ($bundle_settings['index']) {
              $data_sets[] = [
                'bundle_settings' => $bundle_settings,
                'bundle_name' => $bundle_name,
                'entity_type_name' => $entity_type_name,
                'keys' => $sitemap_entity_types[$entity_type_name]->getKeys(),
              ];
            }
          }
        }
      }
    }

    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($context, $entity) {

    $entity_id = $entity->id();
    $entity_type_name = $entity->getEntityTypeId();

    $entity_settings = $this->generator->getEntityInstanceSettings($context, $entity_type_name, $entity_id);

    if (empty($entity_settings['index'])) {
      return FALSE;
    }

    $url_object = $entity->toUrl();

    // Do not include external paths.
    if (!$url_object->isRouted()) {
      return FALSE;
    }

    $path = $url_object->getInternalPath();

    if ($this->batchSettings['remove_duplicates_by_context'] && $this->pathProcessedByContext($context, $path)) {
      return FALSE;
    }
    // Do not include paths that have been already indexed.
    if ($this->batchSettings['remove_duplicates'] && $this->pathProcessed($path)) {
      return FALSE;
    }

    $url_object->setOption('absolute', TRUE);

    return [
      'url' => $url_object,
      'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
      'priority' => isset($entity_settings['priority']) ? $entity_settings['priority'] : NULL,
      'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
      'images' => !empty($entity_settings['include_images'])
        ? $this->getImages($entity_type_name, $entity_id)
        : [],
      'context' => $context,
      // Additional info useful in hooks.
      'meta' => [
        'path' => $path,
        'entity_info' => [
          'entity_type' => $entity_type_name,
          'id' => $entity_id,
          'bundle' => $entity->bundle(),
        ],
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBatchIterationElements($entity_info) {
    $query = $this->entityTypeManager->getStorage($entity_info['entity_type_name'])->getQuery();

    if (!empty($entity_info['keys']['id'])) {
      $query->sort($entity_info['keys']['id'], 'ASC');
    }
    if (!empty($entity_info['keys']['bundle'])) {
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    }
    if (!empty($entity_info['keys']['status'])) {
      $query->condition($entity_info['keys']['status'], 1);
    }

    if ($this->needsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatch($count_query->count()->execute());
    }

    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchSettings['batch_process_limit']);
    }

    return $this->entityTypeManager
      ->getStorage($entity_info['entity_type_name'])
      ->loadMultiple($query->execute());
  }
}
