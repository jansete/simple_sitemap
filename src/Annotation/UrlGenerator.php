<?php

namespace Drupal\simple_sitemap\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a UrlGenerator item annotation object.
 *
 * @package Drupal\simple_sitemap\Annotation
 *
 * @see \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager
 * @see plugin_api
 *
 * @Annotation
 */
class UrlGenerator extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * A short description of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * An integer to determine the weight of this generator relative to others.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * Whether this plugin is enabled or disabled by default.
   *
   * @var bool
   */
  public $enabled = TRUE;

  /**
   * The default settings for the plugin.
   *
   * @var array
   */
  public $settings = [];
}
