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

namespace FireHub\Tests\Container\Unit;

use FireHub\Testing\FireHubTestCase;
use FireHub\Container\Container;
use FireHub\Container\Exception\ {
    CircularDependencyException, ScopeClosedException, ScopeRequiredException, ServiceTypeMismatchException
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    CollectionAbstractLogger, CollectionAlternateImplementation, CollectionConsoleLogger,
    CollectionConcreteAbstractLogger, CollectionDatabaseLogger, CollectionFileLogger, CollectionLogger,
    CollectionOtherService, CollectionUnrelatedService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests collection service resolution
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class CollectionServicesTest extends FireHubTestCase {

    /**
     * @since 1.0.0
     */
    use ExceptionAssertions;

    /**
     * ### All returns empty for unknown implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllReturnsEmptyForUnknownImplementations ():void {

        $container = new Container;

        self::assertSame([], $container->all(CollectionLogger::class));

        $scope = $container->createScope();

        try {

            self::assertSame([], $scope->all(CollectionLogger::class));

        } finally {

            $scope->close();

        }

    }

    /**
     * ### All preserves registration order and filters types
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllPreservesRegistrationOrderAndFiltersTypes ():void {

        $container = new Container;

        $container->singleton(CollectionDatabaseLogger::class);
        $container->singleton(CollectionUnrelatedService::class);
        $container->singleton(CollectionFileLogger::class);

        $services = $container->all(CollectionLogger::class);

        self::assertCount(2, $services);
        self::assertInstanceOf(CollectionDatabaseLogger::class, $services[0]);
        self::assertInstanceOf(CollectionFileLogger::class, $services[1]);

    }

    /**
     * ### All does not discover unregistered classes
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllDoesNotDiscoverUnregisteredClasses ():void {

        self::assertSame(
            [],
            (new Container)->all(CollectionLogger::class)
        );

    }

    /**
     * ### All supports concrete types
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllSupportsConcreteType ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);

        self::assertCount(
            1,
            $container->all(CollectionFileLogger::class)
        );

    }

    /**
     * ### All supports abstract types
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllSupportsAbstractType ():void {

        $container = new Container;

        $container->singleton(CollectionConcreteAbstractLogger::class);

        $services = $container->all(CollectionAbstractLogger::class);

        self::assertCount(1, $services);
        self::assertInstanceOf(
            CollectionConcreteAbstractLogger::class,
            $services[0]
        );

    }

    /**
     * ### All supports inherited interfaces
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllSupportsInheritedInterfaces ():void {

        $container = new Container;

        $container->singleton(CollectionAlternateImplementation::class);

        self::assertCount(
            1,
            $container->all(CollectionLogger::class)
        );

    }

    /**
     * ### All reuses singletons
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllReusesSingletons ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);

        self::assertSame(
            $container->all(CollectionLogger::class)[0],
            $container->all(CollectionLogger::class)[0]
        );

    }

    /**
     * ### All recreates transients
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllRecreatesTransients ():void {

        $container = new Container;

        $container->transient(CollectionFileLogger::class);

        self::assertNotSame(
            $container->all(CollectionLogger::class)[0],
            $container->all(CollectionLogger::class)[0]
        );

    }

    /**
     * ### All requires scope for scoped services
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllRequiresScopeForScopedServices ():void {

        $container = new Container;

        $container->scoped(CollectionFileLogger::class);

        $this->assertThrows(
            ScopeRequiredException::class,
            static function () use ($container):void {

                $container->all(CollectionLogger::class);

            }
        );

    }

    /**
     * ### Scope::all preserves mixed lifetimes
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeAllPreservesMixedLifetimes ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);
        $container->scoped(CollectionConsoleLogger::class);
        $container->transient(CollectionDatabaseLogger::class);

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        try {

            $first = $firstScope->all(CollectionLogger::class);
            $second = $firstScope->all(CollectionLogger::class);
            $third = $secondScope->all(CollectionLogger::class);

            self::assertCount(3, $first);

            // Singleton instances are shared across scopes.
            self::assertSame($first[0], $second[0]);
            self::assertSame($first[0], $third[0]);

            // Scoped instances are shared within the same scope.
            self::assertSame($first[1], $second[1]);
            self::assertNotSame($first[1], $third[1]);

            // Transient instances are recreated.
            self::assertNotSame($first[2], $second[2]);
            self::assertNotSame($first[2], $third[2]);

        } finally {

            $firstScope->close();
            $secondScope->close();

        }

    }

    /**
     * ### Scope::all rejects closed scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeAllRejectsClosedScope ():void {

        $scope = (new Container)->createScope();

        $scope->close();

        $this->assertThrows(
            ScopeClosedException::class,
            static function () use ($scope):void {

                $scope->all(CollectionLogger::class);

            }
        );

    }

    /**
     * ### All deduplicates alias after target
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllDeduplicatesAliasAfterTarget ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);
        $container->alias(CollectionLogger::class, CollectionFileLogger::class);

        $services = $container->all(CollectionLogger::class);

        self::assertCount(1, $services);
        self::assertSame(
            $container->get(CollectionFileLogger::class),
            $services[0]
        );

    }

    /**
     * ### All deduplicates alias before target
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllDeduplicatesAliasBeforeTarget ():void {

        $container = new Container;

        $container->alias(CollectionLogger::class, CollectionFileLogger::class);
        $container->singleton(CollectionFileLogger::class);

        $services = $container->all(CollectionLogger::class);

        self::assertCount(1, $services);
        self::assertSame(
            $container->get(CollectionFileLogger::class),
            $services[0]
        );

    }

    /**
     * ### All resolves factory without eager discovery
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllResolvesFactoryWithoutEagerDiscovery ():void {

        $container = new Container;

        $calls = 0;

        $container->factory(
            CollectionFileLogger::class,
            static function ($resolver) use (&$calls):object {

                $calls++;

                return new CollectionFileLogger;

            }
        );

        self::assertSame(0, $calls);

        $services = $container->all(CollectionLogger::class);

        self::assertCount(1, $services);
        self::assertSame(1, $calls);

    }

    /**
     * ### All rejects factory returning incompatible type
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllRejectsFactoryReturningIncompatibleType ():void {

        $container = new Container;

        $container->factory(
            CollectionFileLogger::class,
            static fn ($resolver):object => new CollectionOtherService
        );

        $this->assertThrows(
            ServiceTypeMismatchException::class,
            static function () use ($container):void {

                $container->all(CollectionLogger::class);

            }
        );

    }

    /**
     * ### All works after a freeze
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllWorksAfterFreeze ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);
        $container->freeze();

        self::assertCount(
            1,
            $container->all(CollectionLogger::class)
        );

    }

    /**
     * ### All detects self-referential alias
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllDetectsSelfReferentialAlias ():void {

        $container = new Container;

        $container->alias(
            CollectionLogger::class,
            CollectionLogger::class
        );

        $this->assertThrows(
            CircularDependencyException::class,
            static function () use ($container):void {

                $container->all(CollectionLogger::class);

            }
        );

    }

    /**
     * ### All detects multi-step alias cycle
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllDetectsMultiStepAliasCycle ():void {

        $container = new Container;

        $container->alias(
            CollectionLogger::class,
            CollectionFileLogger::class
        );

        $container->alias(
            CollectionFileLogger::class,
            CollectionConsoleLogger::class
        );

        $container->alias(
            CollectionConsoleLogger::class,
            CollectionLogger::class
        );

        $this->assertThrows(
            CircularDependencyException::class,
            static function () use ($container):void {

                $container->all(CollectionLogger::class);

            }
        );

    }

    /**
     * ### All matches instance definitions
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllMatchesInstanceDefinitions ():void {

        $container = new Container;

        $instance = new CollectionFileLogger;

        $container->instance(
            CollectionFileLogger::class,
            $instance
        );

        self::assertSame(
            $instance,
            $container->all(CollectionLogger::class)[0]
        );

    }

    /**
     * ### All reflects registrations after earlier lookup
     * @since 1.0.0
     *
     * @return void
     */
    public function testAllReflectsRegistrationsAfterEarlierLookup ():void {

        $container = new Container;

        self::assertSame(
            [],
            $container->all(CollectionLogger::class)
        );

        $container->singleton(CollectionFileLogger::class);

        self::assertCount(
            1,
            $container->all(CollectionLogger::class)
        );

    }

}