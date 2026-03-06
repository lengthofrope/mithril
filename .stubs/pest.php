<?php

/**
 * Intelephense stub for Pest's test() function.
 *
 * Provides $this type binding so the IDE resolves HTTP assertion methods
 * on closures passed to test(). This file is not executed at runtime.
 */

/**
 * @param  string  $description
 * @param  \Closure(\Tests\TestCase $this): void  $closure
 */
function test(string $description, Closure $closure): void {}

/**
 * @param  string  $description
 * @param  \Closure(\Tests\TestCase $this): void  $closure
 */
function it(string $description, Closure $closure): void {}
