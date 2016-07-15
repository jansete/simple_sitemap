<?php

/**
 * @file
 * Contains \Drupal\simple_sitemap\Form\SimplesitemapSettingsForm.
 */

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * SimplesitemapSettingsFrom
 */
class SimplesitemapSettingsForm extends ConfigFormBase {

  private $form_settings = ['max_links', 'cron_generate', 'remove_duplicates', 'batch_process_limit'];

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_sitemap_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_sitemap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $generator = \Drupal::service('simple_sitemap.generator');

    $form['simple_sitemap_settings']['#prefix'] = "<div class='description'>" .t("If you would like to say thanks and support the development of this module, a <a target='_blank' href='@url'>donation</a> is always appreciated.", ['@url' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5AFYRSBLGSC3W']) . "</div>";

    $form['simple_sitemap_settings']['regenerate'] = [
      '#title' => t('Regenerate sitemap'),
      '#type' => 'fieldset',
      '#markup' => '<p>' . t('This will regenerate the XML sitemap for all languages.') . '</p>',
    ];

    $form['simple_sitemap_settings']['regenerate']['regenerate_submit'] = [
      '#type' => 'submit',
      '#value' => t('Regenerate sitemap'),
      '#submit' => ['::generateSitemap'],
      '#validate' => [], // Skip form-level validator.
    ];

    $form['simple_sitemap_settings']['settings'] = [
      '#title' => t('Settings'),
      '#type' => 'fieldset',
    ];

    $form['simple_sitemap_settings']['settings']['cron_generate'] = [
      '#type' => 'checkbox',
      '#title' => t('Regenerate the sitemap on every cron run'),
      '#description' => t('Uncheck this if you intend to only regenerate the sitemap manually or via drush.'),
      '#default_value' => $generator->getSetting('cron_generate'),
    ];

    $form['simple_sitemap_settings']['advanced'] = [
      '#title' => t('Advanced settings'),
      '#type' => 'details',
    ];

    $form['simple_sitemap_settings']['advanced']['remove_duplicates'] = [
      '#type' => 'checkbox',
      '#title' => t('Exclude duplicate links'),
      '#description' => t('Uncheck this to significantly speed up the sitemap generation process on a huge site (more than 20 000 indexed entities).'),
      '#default_value' => $generator->getSetting('remove_duplicates'),
    ];

    $form['simple_sitemap_settings']['advanced']['max_links'] = [
      '#title' => t('Maximum links in a sitemap'),
      '#description' => t("The maximum number of links one sitemap can hold. If more links are generated than set here, a sitemap index will be created and the links split into several sub-sitemaps.<br/>50 000 links is the maximum Google will parse per sitemap, however it is advisable to set this to a lower number. If left blank, all links will be shown on a single sitemap."),
      '#type' => 'textfield',
      '#maxlength' => 5,
      '#size' => 5,
      '#default_value' => $generator->getSetting('max_links'),
    ];

    $form['simple_sitemap_settings']['advanced']['batch_process_limit'] = [
      '#title' => t('Refresh batch every n links'),
      '#description' => t("During sitemap generation, the batch process will issue a page refresh after n links processed to prevent PHP timeouts and memory exhaustion. Increasing this number will reduce the number of times Drupal has to bootstrap (thus speeding up the generation process), but will require more memory and less strict PHP timeout settings."),
      '#type' => 'textfield',
      '#maxlength' => 5,
      '#size' => 5,
      '#default_value' => $generator->getSetting('batch_process_limit'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $max_links = $form_state->getValue('max_links');
    if ($max_links != '') {
      if (!is_numeric($max_links) || $max_links < 1 || $max_links != round($max_links)) {
        $form_state->setErrorByName('', t("The value of the <em>Maximum links in a sitemap</em> field must be empty, or a positive integer greater than 0."));
      }
    }

  $batch_process_limit = $form_state->getValue('batch_process_limit');
    if (!is_numeric($batch_process_limit) || $batch_process_limit < 1 || $batch_process_limit != round($batch_process_limit)) {
      $form_state->setErrorByName('', t("The value of the <em>Refresh batch every n links</em> field must be a positive integer greater than 0."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $generator = \Drupal::service('simple_sitemap.generator');
    foreach($this->form_settings as $setting_name) {
      $generator->saveSetting($setting_name, $form_state->getValue($setting_name));
    }
    parent::submitForm($form, $form_state);
  }

  public function generateSitemap(array &$form, FormStateInterface $form_state) {
    $generator = \Drupal::service('simple_sitemap.generator');
    $generator->generateSitemap();
  }
}
