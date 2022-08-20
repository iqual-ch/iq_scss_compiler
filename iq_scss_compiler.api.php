<?php

/**
 * @file
 * Hooks related to SCSS compiler module.
 */

/**
 * Called before the compilation starts.
 *
 * @param bool $cli
 *   Whether the call originated from cli.
 * @param \AppendIterator &$iterator
 *   The file iterator.
 */
function hook_iq_scss_compiler_pre_compile($cli = TRUE, \AppendIterator $iterator = NULL) {
}

/**
 * Called before the compilation starts.
 *
 * @param bool $cli
 *   Whether the call originated from cli.
 * @param \AppendIterator &$iterator
 *   The file iterator.
 */
function hook_iq_scss_compiler_post_compile($cli = TRUE, \AppendIterator $iterator = NULL) {
}
