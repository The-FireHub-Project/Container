<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container
 */

namespace FireHub\Tests\Container\Unit;

use FireHub\Container\Container;
use FireHub\Container\Exception\ScopeRequiredException;
use FireHub\Testing\FireHubTestCase;
use FireHub\Tests\Container\Stubs\ {
    LifetimeValidationNestedSingleton, LifetimeValidationScoped,
    LifetimeValidationSingleton, LifetimeValidationTransient
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests runtime service lifetime protection
 * @since 1.0.0
 */
#[Small]
#[Group('lifetime')]
#[CoversClass(Container::class)]
final class LifetimeRuntimeTest extends FireHubTestCase {

    /**
     * ### Rejects direct singleton-to-scoped dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testDirectSingletonScopedDependency ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->singleton(LifetimeValidationSingleton::class);

        $scope = $container->createScope();

        try {

            $this->expectException(ScopeRequiredException::class);

            $scope->get(LifetimeValidationSingleton::class);

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Rejects transitive singleton-to-scoped dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testTransitiveSingletonScopedDependency ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->transient(LifetimeValidationTransient::class);
        $container->singleton(LifetimeValidationNestedSingleton::class);

        $scope = $container->createScope();

        try {

            $this->expectException(ScopeRequiredException::class);

            $scope->get(LifetimeValidationNestedSingleton::class);

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Allows scoped dependencies inside transient services
     * @since 1.0.0
     *
     * @return void
     */
    public function testTransientScopedDependency ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->transient(LifetimeValidationTransient::class);

        $scope = $container->createScope();

        try {

            $first = $scope->get(LifetimeValidationTransient::class);
            $second = $scope->get(LifetimeValidationTransient::class);

            self::assertNotSame($first, $second);
            self::assertSame($first->dependency, $second->dependency);

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Rejects singleton-to-scoped dependencies from root resolution
     * @since 1.0.0
     *
     * @return void
     */
    public function testRootSingletonScopedDependency ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->singleton(LifetimeValidationSingleton::class);

        $this->expectException(ScopeRequiredException::class);

        $container->get(LifetimeValidationSingleton::class);

    }

}