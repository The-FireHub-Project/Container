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
use FireHub\Container\Container;

/**
 * ### Disposable service that attempts resolution during disposal
 * @since 1.0.0
 */
final class ResolvingDisposableTestService implements Disposable {

    /**
     * ### Constructor
     * @param Container $container Parent container.
     * @since 1.0.0
     *
     */
    public function __construct(
        private Container $container
    ) {}

    /**
     * ### Attempts resolution during disposal
     * @return void
     * @throws ContainerClosedException If the container is closed.
     *
     * @since 1.0.0
     *
     */
    public function dispose(): void {

        $this->container->get(DisposableTestService::class);

    }

}