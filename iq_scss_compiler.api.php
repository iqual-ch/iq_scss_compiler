<?php

/**
 * @file
 * Hooks related to SCSS compiler module.
 */

use Drupal\iq_scss_compiler\Service\CompilationService;

/**
 * Called before the compilation starts.
 *
 * @param CompilationService $compilationService
 *   The active compilation service.
 */
function hook_iq_scss_compiler_pre_compile(CompilationService $compilationService)
{
}

/**
 * Called after the compilation completes.
 *
 * @param CompilationService $compilationService
 *   The active compilation service.
 */
function hook_iq_scss_compiler_post_compile(CompilationService $compilationService)
{
}

/**
 * Called before saving the compiled css to the target file.
 *
 * @param string $css
 *   The compiled css.
 * @param array $context
 *   Arry providing involved files under indexes source and target and the compilation service.
 */
function hook_iq_scss_compiler_css(string &$css, array &$context)
{
}
