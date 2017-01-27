<?php

namespace Drupal\administration_language_negotiation\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the Administration Language Negotiation language negotiation method.
 */
class NegotiationLanguageAdministrationLanguageNegotiationForm extends ConfigFormBase implements ContainerInjectionInterface {

  /**
   * The variable containing the conditions configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The administration language negotiation condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $administrationLanguageNegotiationConditionManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * NegotiationLanguageAdministrationLanguageNegotiationForm constructor.
   *
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $plugin_manager
   *   The plugin manager.
   */
  public function __construct(ExecutableManagerInterface $plugin_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($this->configFactory());
    $this->administrationLanguageNegotiationConditionManager = $plugin_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.administration_language_negotiation_condition'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_negotiation_configure_administration_language_negotiation_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['administration_language_negotiation.negotiation'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->config = $this->config('administration_language_negotiation.negotiation');
    $manager = $this->administrationLanguageNegotiationConditionManager;

    $languages = $this->languageManager->getLanguages();

    $options = [];
    foreach ($languages as $language) {
      $options[$language->getId()] = $language->getName();
    }

    $form['default_language'] = array(
      '#title' => 'Default language',
      '#type' => 'radios',
      '#default_value' => $this->config->get('default_language'),
      '#options' => $options,
    );

    foreach ($manager->getDefinitions() as $def) {
      $condition_plugin = $manager->createInstance($def['id']);
      $form_state->set(['conditions', $condition_plugin->getPluginId()], $condition_plugin);

      $condition_plugin->setConfiguration($condition_plugin->getConfiguration() + (array) $this->config->get());

      $condition_form = [];
      $condition_form['#markup'] = $condition_plugin->getDescription();
      $condition_form += $condition_plugin->buildConfigurationForm([], $form_state);

      if (!empty($condition_form[$condition_plugin->getPluginId()])) {
        $condition_form['#type'] = 'details';
        $condition_form['#open'] = TRUE;
        $condition_form['#title'] = $condition_plugin->getName();
        $condition_form['#weight'] = $condition_plugin->getWeight();
        $form['conditions'][$condition_plugin->getPluginId()] = $condition_form;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\administration_language_negotiation\AdministrationLanguageNegotiationConditionInterface $condition */
    foreach ($form_state->get(['conditions']) as $condition) {
      $condition->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\administration_language_negotiation\AdministrationLanguageNegotiationConditionInterface $condition */
    foreach ($form_state->get(['conditions']) as $condition) {
      $condition->submitConfigurationForm($form, $form_state);
      if (isset($condition->getConfiguration()[$condition->getPluginId()])) {
        $this->config
          ->set($condition->getPluginId(), $condition->getConfiguration()[$condition->getPluginId()]);
      }
    }

    $this->config->set('default_language', $form_state->getValue('default_language'));
    $this->config->save();

    /** @var \Drupal\administration_language_negotiation\AdministrationLanguageNegotiationConditionInterface $condition */
    foreach ($form_state->get(['conditions']) as $condition) {
      $condition->postConfigSave($form, $form_state);
    }

    // Redirect to the language negotiation page on submit (previous Drupal 7
    // behavior, and intended behavior for other language negotiation settings
    // forms in Drupal 8 core).
    $form_state->setRedirect('language.negotiation');
  }


}
