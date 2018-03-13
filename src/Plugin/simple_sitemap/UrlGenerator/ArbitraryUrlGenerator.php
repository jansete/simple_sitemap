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
 * Class ArbitraryUrlGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "arbitrary",
 *   title = @Translation("Arbitrary URL generator"),
 *   description = @Translation("Generates URLs from data sets collected in the hook_arbitrary_links_alter hook."),
 *   enabled = TRUE,
 *   weight = 20,
 *   settings = {
 *   },
 * )
 */
class ArbitraryUrlGenerator extends UrlGeneratorBase {

  /**
   * ArbitraryUrlGenerator constructor.
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
    ModuleHandlerInterface $module_handler
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
      $container->get('module_handler')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getDataSets($context) {
    $arbitrary_links = [];
    $this->moduleHandler->alter('simple_sitemap_arbitrary_links', $arbitrary_links);

    if (!empty($arbitrary_links)) {
      foreach ($arbitrary_links as $key => $link) {
        if (empty($link['context'])) {
          $arbitrary_links[$key]['context'] = Simplesitemap::CONTEXT_DEFAULT;
        }
      }
      foreach ($arbitrary_links as $key => $link) {
        if ($link['context'] !== $context && $link['context'] !== Simplesitemap::CONTEXT_DEFAULT) {
          unset($arbitrary_links[$key]);
        }
      }
    }

    return array_values($arbitrary_links);
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($context, $data_set) {
    return $data_set;
  }
}
