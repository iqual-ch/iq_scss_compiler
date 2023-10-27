<?php

namespace Drupal\iq_scss_compiler\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

/**
 * Compilation service for watching directories and compiling sass files.
 */
class CompilationService {

  /**
   * The sources iterator.
   *
   * @var \AppendIterator
   */
  protected $iterator = NULL;

  /**
   * Configs encountered in source directories.
   *
   * @var array
   */
  protected $configs = [];

  /**
   * The scss/sass compiler.
   *
   * @var \Sass
   */
  protected $compiler = NULL;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger = NULL;


  /**
   * Whether the call originated on cli.
   *
   * @var bool
   */
  protected $isCli = PHP_SAPI === 'cli';

  /**
   * The watch pause flag file.
   */
  final public const WATCH_FILE = '/tmp/iqsc_watch_paused';

  /**
   * The compilation flag file.
   */
  final public const COMPILE_FILE = '/tmp/iqsc_compiling';

  /**
   * Create compilation service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->logger = $loggerChannelFactory->get('iq_scss_compiler');
    $this->iterator = new \AppendIterator();
    $this->compiler = new Compiler();
    $this->compiler->setOutputStyle(OutputStyle::COMPRESSED);

    // Reset state to be sure.
    if ($this->isPaused() && filemtime(static::WATCH_FILE) - 300 > time()) {
      $this->resumeWatch();
    }
    if ($this->isCompiling() && filemtime(static::COMPILE_FILE) - 300 > time()) {
      $this->stopCompilation();
    }
  }

  /**
   * Add a source directory for watching/compilation.
   *
   * @param string $directory
   *   The directory path.
   */
  public function addSource(string $directory) {
    if (is_dir($directory)) {
      $files = new \RecursiveDirectoryIterator($directory);
      $recursiveIterator = new \RecursiveIteratorIterator($files);
      $this->iterator->append($recursiveIterator);
    }
  }

  /**
   * Is Cli.
   */
  public function isCli() {
    return $this->isCli;
  }

  /**
   * Pause watching.
   */
  public function pauseWatch() {
    touch(static::WATCH_FILE);
  }

  /**
   * Unpause watching.
   */
  public function resumeWatch() {
    unlink(static::WATCH_FILE);
  }

  /**
   * Whether watching is paused.
   *
   * @return bool
   *   Whether watching is paused.
   */
  public function isPaused() {
    return file_exists(static::WATCH_FILE);
  }

  /**
   * Create compilation flag.
   */
  public function startCompilation() {
    touch(static::COMPILE_FILE);
  }

  /**
   * Remove compilation flag.
   */
  public function stopCompilation() {
    unlink(static::COMPILE_FILE);
  }

  /**
   * Whether compilation is running.
   *
   * @return bool
   *   Whether compilation is running.
   */
  public function isCompiling() {
    return file_exists(static::COMPILE_FILE);
  }

  /**
   * Checks whether the iterator contains any sources and rewinds the iterator.
   *
   * @return bool
   *   True, when there are any sources registered.
   */
  public function hasSources() {
    $count = iterator_count($this->iterator);
    $this->iterator->rewind();
    return $count > 0;
  }

  /**
   * Watch the sources for changes and compile them instantly.
   *
   * @param int $ttl
   *   How long the watcher should run. Defaults to 60 minutes.
   */
  public function watch($ttl = 60) {
    if (!$this->hasSources()) {
      $this->logger->warn('Watcher found no sources');
      return;
    }
    $startTime = time();
    $fd = \inotify_init();
    $changeRegistered = FALSE;

    // Collect all config files and save per path.
    while ($this->iterator->valid()) {
      $file = $this->iterator->current();
      $watch_descriptor = \inotify_add_watch($fd, $file->getPath(), IN_CREATE | IN_CLOSE_WRITE | IN_MOVE | IN_MOVE_SELF | IN_DELETE | IN_DELETE_SELF | IN_MASK_ADD);
      $this->iterator->next();
    }
    $this->iterator->rewind();
    while ($this->iterator->valid()) {
      if (inotify_queue_len($fd) === 0 && $changeRegistered && !$this->isCompiling()) {
        $changeRegistered = FALSE;
        $this->compile();
      }
      $events = \inotify_read($fd);

      if (!$this->isPaused()) {
        foreach ($events as $event => $evdetails) {
          // React on the event type.
          switch (TRUE) {
            // File was created.
            case ($evdetails['mask'] & IN_CREATE):
              // File was modified.
            case (((int) $evdetails['mask']) & IN_CLOSE_WRITE):
              // File was moved.
            case ($evdetails['mask'] & IN_MOVE):
            case ($evdetails['mask'] & IN_MOVE_SELF):
              // File was deleted.
            case ($evdetails['mask'] & IN_DELETE):
            case ($evdetails['mask'] & IN_DELETE_SELF):
              if (preg_match_all('/\.scss$/', (string) $evdetails['name'])) {
                $changeRegistered = TRUE;
              }
              break;
          }
        }
      }
      sleep(1);
      if ($ttl != '*' && ($ttl * 60) + $startTime < time()) {
        exit(0);
      }
    }
  }

  /**
   * Compile any sass files in the source directories.
   *
   * @param bool $continueOnError
   *   Continue on compilation errors.
   * @param bool $verbose
   *   Be verbose about the process.
   */
  public function compile($continueOnError = FALSE, $verbose = FALSE) {
    $this->pauseWatch();
    $this->startCompilation();

    \Drupal::moduleHandler()->invokeAll('iq_scss_compiler_pre_compile', [$this]);

    // Collect all config files and save per path.
    while ($this->iterator->valid()) {
      $file = $this->iterator->current();
      if ($file->isFile() && $file->getFilename() == 'libsass.ini') {
        $this->configs[$file->getPath()] = parse_ini_file($file->getPath() . '/' . $file->getFilename());
      }
      $this->iterator->next();
    }
    $this->iterator->rewind();

    // Compile files, respecting the config in the same directory.
    while ($this->iterator->valid()) {
      $scssFile = $this->iterator->current();
      if ($scssFile->isFile() && $scssFile->getExtension() == 'scss' && !str_starts_with((string) $scssFile->getFilename(), '_')) {
        $sourceFile = $scssFile->getPath() . '/' . $scssFile->getFilename();
        try {
          $css = $this->compiler->compileString('@import "' . $sourceFile . '";')->getCss();
        }
        catch (\Exception $e) {
          if ($continueOnError) {
            if ($verbose) {
              echo $e->getMessage() . "\n\n";
            }
            else {
              $this->logger->error($e->getMessage());
            }
          }
          else {
            throw $e;
          }
        }
        $targetFile = $scssFile->getPath() . '/' . str_replace('scss', 'css', (string) $scssFile->getFilename());
        if (!empty($this->configs[$scssFile->getPath()])) {
          if (!is_dir($scssFile->getPath() . '/' . $this->configs[$scssFile->getPath()]['css_dir'])) {
            mkdir($scssFile->getPath() . '/' . $this->configs[$scssFile->getPath()]['css_dir'], 0755, TRUE);
          }
          $targetFile = $scssFile->getPath() . '/' . $this->configs[$scssFile->getPath()]['css_dir'] . '/' . str_replace('scss', 'css', (string) $scssFile->getFilename());
        }

        // Allow other modules to alter the css before saving it.
        $context = [
          'source' => $sourceFile,
          'target' => $targetFile,
          'service' => $this,
        ];
        \Drupal::moduleHandler()->alter('iq_scss_compiler_css', $css, $context);

        file_put_contents($targetFile, $css);
        if ($verbose) {
          $message = 'Compiled ' . $sourceFile . ' into ' . $targetFile;
          echo $message . "\n";
        }
      }
      $this->iterator->next();
    }
    $this->iterator->rewind();

    \Drupal::moduleHandler()->invokeAll('iq_scss_compiler_post_compile', [$this]);

    $this->stopCompilation();
    $this->resumeWatch();
  }

}
