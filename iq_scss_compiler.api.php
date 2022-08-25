<?php

/**
 * @file
 * Hooks related to SCSS compiler module.
 */

use Drupal\iq_scss_compiler\Service\CompilationService;

/**
 * Called before the compilation starts.
 *
 * @param bool $cli
 *   Whether the call originated from cli.
 * @param \AppendIterator &$iterator
 *   The file iterator.
 */
function hook_iq_scss_compiler_pre_compile(CompilationService $compilationService)
{
}

/**
 * Called before the compilation starts.
 *
 * @param bool $cli
 *   Whether the call originated from cli.
 * @param \AppendIterator &$iterator
 *   The file iterator.
 */
function hook_iq_scss_compiler_post_compile(CompilationService $compilationService)
{
}
