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

namespace FireHub\Tests\Container\Stubs;

use FireHub\Core\Boundary\Lifecycle\Disposable;
use RuntimeException;

/**
 * ### Failing disposable test service
 *
 * Throws an exception during disposal.
 * @since 1.0.0
 */
final class FailingDisposableTestService implements Disposable {

    /**
     * ### Number of disposal calls
     * @since 1.0.0
     */
    public int $disposals = 0;

    /**
     * ### Releases the service
     * @since 1.0.0
     *
     * @return void
     */
    public function dispose ():void {

        $this->disposals++;

        throw new RuntimeException('Disposal failed');

    }

}