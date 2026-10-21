<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container\Tests
 */

namespace FireHub\Tests\Container\Support;

use Closure, Throwable;

/**
 * ### Assertions for exceptions inside nested callbacks
 *
 * Allows tests to validate expected failures and continue asserting state afterward.
 * @since 1.0.0
 */
trait ExceptionAssertions {

    /**
     * ### Asserts that a callback throws an exception of the expected type
     * @since 1.0.0
     *
     * @param class-string<Throwable> $expected Expected exception class.
     * @param Closure():mixed $callback Callback expected to throw.
     *
     * @return void
     */
    private function assertThrows (string $expected, Closure $callback):void {

        try {

            $callback();

        } catch (Throwable $exception) {

            self::assertInstanceOf($expected, $exception);

            return;

        }

        self::fail("Expected exception {$expected} was not thrown.");

    }

}
