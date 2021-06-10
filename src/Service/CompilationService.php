<?php

namespace Drupal\iq_scss_compiler\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 *
 */
class CompilationService {

  protected $iterator = NULL;
  protected $configs = [];
  protected $compiler = NULL;
  protected $changeRegistered = FALSE;
  protected $isCompiling = FALSE;
  
  protected $logger = null;

  const WATCH_FILE = '/tmp/iqsc_watch_paused';
  const COMPILE_FILE = '/tmp/iqsc_compiling';

  /**
   *
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->logger = $loggerChannelFactory->get('iq_scss_compiler');
    $this->iterator = new \AppendIterator();
    $this->compiler = new \Sass();
    $this->compiler->setStyle(\Sass::STYLE_COMPRESSED);

    // Reset state to be sure.
    if ($this->isPaused() && filemtime(static::WATCH_FILE) - 300 > time()) {
      $this->resumeWatch();
    }
    if ($this->isCompiling() && filemtime(static::COMPILE_FILE) - 300 > time()) {
      $this->stopCompilation();
    }
  }

  /**
   *
   */
  public function addSource($directory) {
    if (is_dir($directory)) {
      $files = new \RecursiveDirectoryIterator($directory);
      $recursiveIterator = new \RecursiveIteratorIterator($files);
      $this->iterator->append($recursiveIterator);
    }
  }

  /**
   *
   */
  public function pauseWatch() {
    touch(static::WATCH_FILE);
  }

  /**
   *
   */
  public function resumeWatch() {
    unlink(static::WATCH_FILE);
  }

  /**
   *
   */
  public function isPaused() {
    return file_exists(static::WATCH_FILE);
  }

  /**
   *
   */
  public function startCompilation() {
    touch(static::COMPILE_FILE);
  }

  /**
   *
   */
  public function stopCompilation() {
    unlink(static::COMPILE_FILE);
  }

  /**
   *
   */
  public function isCompiling() {
    return file_exists(static::COMPILE_FILE);
  }

  /**
   * Checks whether the iterator contains any sources and rewinds the iterator.
   *
   * @return bool
   */
  public function hasSources() {
    $count = iterator_count($this->iterator);
    $this->iterator->rewind();
    return $count > 0;
  }

  /**
   *
   */
  public function watch($ttl) {
    if (!$this->hasSources()) {
      $this->logger->warn('Watcher found no sources');
      return;
    }
    $startTime = time();
    $fd = \inotify_init();

    // Collect all config files and save per path.
    while ($this->iterator->valid()) {
      $file = $this->iterator->current();
      $watch_descriptor = \inotify_add_watch($fd, $file->getPath(), IN_CREATE | IN_CLOSE_WRITE | IN_MOVE | IN_MOVE_SELF | IN_DELETE | IN_DELETE_SELF | IN_MASK_ADD);
      $this->iterator->next();
    }
    $this->iterator->rewind();
    while ($this->iterator->valid()) {
      if (inotify_queue_len($fd) === 0 && $this->changeRegistered && !$this->isCompiling()) {
        $this->changeRegistered = FALSE;
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
              if (preg_match_all('/\.scss$/', $evdetails['name'])) {
                $this->changeRegistered = TRUE;
              }
              break;
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
   *
   */
  public function compile($continueOnError = false) {
    $this->pauseWatch();
    $this->startCompilation();
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
      if ($scssFile->isFile() && $scssFile->getExtension() == 'scss' && strpos($scssFile->getFilename(), '_') !== 0) {
        $sourceFile = $scssFile->getPath() . '/' . $scssFile->getFilename();
        try {
          $css = $this->compiler->compileFile($sourceFile);  
        } catch(\Exception $e) {
          if ($continueOnError) {
            if ($verbose) { 
              echo $e->getMessage();
            } else {
              $this->logger->error($e->getMessage());
            }
          } else {
            throw $e;
          }
        }
        $targetFile = $scssFile->getPath() . '/' . str_replace('scss', 'css', $scssFile->getFilename());
        if (!empty($this->configs[$scssFile->getPath()])) {
          if (!is_dir($scssFile->getPath() . '/' . $this->configs[$scssFile->getPath()]['css_dir'])) {
            mkdir($scssFile->getPath() . '/' . $this->configs[$scssFile->getPath()]['css_dir'], 0770, TRUE);
          }
          $targetFile = $scssFile->getPath() . '/' . $this->configs[$scssFile->getPath()]['css_dir'] . '/' . str_replace('scss', 'css', $scssFile->getFilename());
        }
        file_put_contents($targetFile, $css);
        if ($verbose) { 
          $message = 'Compiled ' .  $sourceFile ' into ' . $targetFile;
          $this->logger->error($message);
        }
      }
      $this->iterator->next();
    }
    $this->iterator->rewind();

    $this->stopCompilation();
    $this->resumeWatch();
  }

}
