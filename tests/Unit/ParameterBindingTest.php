<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=8.5
 * @package Container\Tests
 */

namespace FireHub\Tests\Container\Unit;

use FireHub\Testing\FireHubTestCase;
use FireHub\Container\Container;
use FireHub\Container\Exception\ServiceInstantiationException;
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    BoundConnection, BoundDefaultService, BoundMixedService, BoundNullableService, BoundObjectService,
    BoundOtherConnection, Logger
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests explicit parameter bindings
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class ParameterBindingTest extends FireHubTestCase {

    /**
     * @since 1.0.0
     */
    use ExceptionAssertions;

    /**
     * ### Explicit parameter bindings resolve scalar dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitParameterBindingsResolveScalarDependencies ():void {

        $container = new Container();

        $container->bindParameters(
            BoundConnection::class,
            [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'firehub'
            ]
        );

        $service = $container->get(BoundConnection::class);

        self::assertSame('localhost', $service->host);
        self::assertSame(3306, $service->port);
        self::assertSame('firehub', $service->database);

    }

    /**
     * ### Explicit bindings combine with automatic dependency resolution
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsCombineWithAutomaticDependencyResolution ():void {

        $container = new Container();

        $container->bindParameters(
            BoundMixedService::class,
            [
                'host' => 'localhost'
            ]
        );

        $service = $container->get(BoundMixedService::class);

        self::assertInstanceOf(Logger::class, $service->logger);
        self::assertSame('localhost', $service->host);
        self::assertSame(3306, $service->port);

    }

    /**
     * ### Explicit bindings override constructor defaults
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsOverrideConstructorDefaults ():void {

        $container = new Container();

        $container->bindParameters(
            BoundDefaultService::class,
            [
                'port' => 9000
            ]
        );

        $service = $container->get(BoundDefaultService::class);

        self::assertSame(9000, $service->port);

    }

    /**
     * ### Explicit bindings support null values
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsSupportNullValues ():void {

        $container = new Container();

        $container->bindParameters(
            BoundNullableService::class,
            [
                'logger' => null
            ]
        );

        $service = $container->get(BoundNullableService::class);

        self::assertNull($service->logger);

    }

    /**
     * ### Explicit bindings support object instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsSupportObjectInstances ():void {

        $container = new Container();

        $logger = new Logger();

        $container->bindParameters(
            BoundObjectService::class,
            [
                'logger' => $logger
            ]
        );

        $service = $container->get(BoundObjectService::class);

        self::assertSame($service->logger, $logger);

    }

    /**
     * ### Explicit bindings are isolated by implementation class
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsAreIsolatedByImplementationClass ():void {

        $container = new Container();

        $container->bindParameters(
            BoundConnection::class,
            [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'firehub'
            ]
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(BoundOtherConnection::class);

            }
        );

    }

    /**
     * ### Explicit bindings preserve transient lifetimes
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsPreserveTransientLifetimes ():void {

        $container = new Container();

        $container->bindParameters(
            BoundDefaultService::class,
            [
                'port' => 9000
            ]
        );

        $first = $container->get(BoundDefaultService::class);
        $second = $container->get(BoundDefaultService::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->port, $second->port);

    }

    /**
     * ### Explicit bindings work with singleton services
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsWorkWithSingletonServices ():void {

        $container = new Container();

        $container->singleton(BoundDefaultService::class);

        $container->bindParameters(
            BoundDefaultService::class,
            [
                'port' => 9000
            ]
        );

        $first = $container->get(BoundDefaultService::class);
        $second = $container->get(BoundDefaultService::class);

        self::assertSame($first, $second);
        self::assertSame(9000, $first->port);

    }

    /**
     * ### Explicit bindings respect container freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitBindingsRespectContainerFreezing ():void {

        $container = new Container();

        $container->freeze();

        $this->assertThrows(
            \FireHub\Container\Exception\ContainerFrozenException::class,
            function () use ($container):void {

                $container->bindParameters(
                    BoundDefaultService::class,
                    [
                        'port' => 9000
                    ]
                );

            }
        );

    }

}
