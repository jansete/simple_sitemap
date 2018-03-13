<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

/**
 * Interface UrlGeneratorInterface
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 */
interface UrlGeneratorInterface {

  /**
   * @param $context
   *
   * @return mixed
   */
  public function generate($context);

  /**
   * @param $context
   *
   * @return mixed
   */
  public function getDataSets($context);
}
