<?php

namespace Drupal\iq_scss_compiler\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\iq_scss_compiler\Service\CompilationService;
use Drush\Commands\DrushCommands;

/**
 * Sass Drush commands.
 */
class SassCommands extends DrushCommands {

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;

  /**
   * The compilation service.
   *
   * @var \Drupal\iq_scss_compiler\Service\CompilationService
   */
  protected $compilationService;

  /**
   * Constructs a new SassCommands object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory service.
   * @param \Drupal\iq_scss_compiler\Service\CompilationService $compilation_service
   *   The compilation service.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory, CompilationService $compilation_service) {
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->compilationService = $compilation_service;
  }

  /**
   * Import all products and certificates.
   *
   * @options folders Whether or not an extra message should be displayed to the user.
   *
   * @command iq_scss_compiler:watch
   * @aliases iq_scss_compiler-watch
   * @aliases iqsc:watch
   *
   * @usage drush iq_scss_compiler:watch --folders=themes,modules
   */
  public function watch($options = ['folders' => 'themes', 'ttl' => 60]) {
    $folders = explode(',', str_replace('}', '', str_replace('{', '', $options['folders'])));
    $ttl = $options['ttl'];

    foreach ($folders as $folder) {
      $folder = trim($folder);
      if (!empty($folder)) {
        $this->compilationService->addSource(DRUPAL_ROOT . '/' . $folder);
      }
    }
    echo 'Starting sass watch' . "\n";
    $this->compilationService->watch($ttl);
  }

  /**
   * Compile scss.
   *
   * @options folders Whether or not an extra message should be displayed to the user.
   *
   * @command iq_scss_compiler:compile
   * @aliases iq_scss_compiler-compile
   * @aliases iqsc:compile
   *
   * @usage drush iq_scss_compiler:compile --folders=themes/custom,modules/custom,sites/default/files/styling_profiles --continueOnErrors=false --verbose=false
   */
  public function compile($options = [
    'folders' => 'themes/custom,modules/custom,sites/default/files/styling_profiles',
    'continueOnErrors' => FALSE,
  ]) {
    $folders = explode(',', str_replace('}', '', str_replace('{', '', $options['folders'])));

    foreach ($folders as $folder) {
      $folder = trim($folder);
      if (!empty($folder)) {
        $this->compilationService->addSource(\Drupal::root() . '/' . $folder);
      }
    }
    echo 'Compiling SASS' . "\n";
    $this->compilationService->compile();
  }

}
