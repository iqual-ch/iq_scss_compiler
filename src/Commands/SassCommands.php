<?php

namespace Drupal\iq_scss_compiler\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Sass Drush commands.
 */
class SassCommands extends DrushCommands {

  /**
   * Constructs a new SassCommands object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory service.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->loggerChannelFactory = $loggerChannelFactory;
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

    $compilationService = \Drupal::service('iq_scss_compiler.compilation_service');
    foreach ($folders as $folder) {
      $folder = trim($folder);
      if (!empty($folder)) {
        $compilationService->addSource(DRUPAL_ROOT . '/' . $folder);
      }
    }
    echo 'Starting sass watch' . "\n";
    $compilationService->watch($ttl);
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

    $compilationService = \Drupal::service('iq_scss_compiler.compilation_service');
    foreach ($folders as $folder) {
      $folder = trim($folder);
      if (!empty($folder)) {
        $compilationService->addSource(\Drupal::root() . '/' . $folder);
      }
    }
    echo 'Compiling SASS' . "\n";
    $compilationService->compile();
  }

}
