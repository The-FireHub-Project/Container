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
use FireHub\Container\Exception\ {
    CircularDependencyException, ScopeRequiredException, ServiceInstantiationException};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    CollectionConsoleLogger, CollectionFileLogger, CollectionInjectionCircularA, CollectionInjectionCircularB,
    CollectionInjectionConflictingAttributesConsumer, CollectionInjectionConsumer, CollectionInjectionDefaultConsumer,
    CollectionInjectionEmptyTypeConsumer, CollectionInjectionMultipleAttributesConsumer,
    CollectionInjectionNestedConsumer, CollectionInjectionNullableConsumer, CollectionInjectionScalarConsumer,
    CollectionInjectionUnknownTypeConsumer, CollectionInjectionUntypedConsumer, CollectionUnrelatedService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests collection constructor injection
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class CollectionInjectionTest extends FireHubTestCase {

    /**
     * @since 1.0.0
     */
    use ExceptionAssertions;

    /**
     * ### Collection attribute injects services in registration order
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectsRegisteredImplementationsInRegistrationOrder ():void {

        $container = new Container;

        $container->singleton(CollectionConsoleLogger::class);
        $container->singleton(CollectionUnrelatedService::class);
        $container->singleton(CollectionFileLogger::class);

        $consumer = $container->get(CollectionInjectionConsumer::class);

        self::assertCount(2, $consumer->loggers);
        self::assertInstanceOf(CollectionConsoleLogger::class, $consumer->loggers[0]);
        self::assertInstanceOf(CollectionFileLogger::class, $consumer->loggers[1]);

    }

    /**
     * ### Collection attribute injects an empty array for no registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testNoRegistrationsInjectEmptyArray ():void {

        $consumer = (new Container)->get(CollectionInjectionConsumer::class);

        self::assertSame([], $consumer->loggers);

    }

    /**
     * ### Collection attribute ignores unregistered implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnregisteredConstructibleImplementationIsNotDiscovered ():void {

        $container = new Container;

        $consumer = $container->get(CollectionInjectionConsumer::class);

        self::assertSame([], $consumer->loggers);

    }

    /**
     * ### Collection attribute overrides constructor default
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionOverridesConstructorDefault ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);

        $consumer = $container->get(CollectionInjectionDefaultConsumer::class);

        self::assertCount(1, $consumer->loggers);

    }

    /**
     * ### Collection attribute overrides parameter binding
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitParameterBindingOverridesInjection ():void {

        $container = new Container;

        $override = [new CollectionConsoleLogger];

        $container->bindParameters(
            CollectionInjectionConsumer::class,
            ['loggers' => $override]
        );

        $consumer = $container->get(CollectionInjectionConsumer::class);

        self::assertSame($override, $consumer->loggers);

    }

    /**
     * ### Collection attribute works in nested dependency graphs
     * @since 1.0.0
     *
     * @return void
     */
    public function testNestedGraphUsesCollectionInjection ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);

        $nested = $container->get(CollectionInjectionNestedConsumer::class);

        self::assertInstanceOf(
            CollectionFileLogger::class,
            $nested->consumer->loggers[0]
        );

    }

    /**
     * ### Collection attribute uses singleton instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionUsesSingletonInstances ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);

        $first = $container->get(CollectionInjectionConsumer::class);
        $second = $container->get(CollectionInjectionConsumer::class);

        self::assertSame($first->loggers[0], $second->loggers[0]);
        self::assertSame(
            $first->loggers[0],
            $container->get(CollectionFileLogger::class)
        );

    }

    /**
     * ### Collection attribute creates transient instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionCreatesTransientInstances ():void {

        $container = new Container;

        $container->transient(CollectionFileLogger::class);

        $first = $container->get(CollectionInjectionConsumer::class);
        $second = $container->get(CollectionInjectionConsumer::class);

        self::assertNotSame($first->loggers[0], $second->loggers[0]);

    }

    /**
     * ### Collection attribute requires a scope for scoped services
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionRequiresScopeForScopedServices ():void {

        $container = new Container;

        $container->scoped(CollectionFileLogger::class);

        $this->assertThrows(
            ScopeRequiredException::class,
            static function () use ($container):void {

                $container->get(CollectionInjectionConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute reuses scoped instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionReusesScopedInstances ():void {

        $container = new Container;

        $container->scoped(CollectionFileLogger::class);

        $scope = $container->createScope();

        try {

            $first = $scope->get(CollectionInjectionConsumer::class);
            $second = $scope->get(CollectionInjectionConsumer::class);

            self::assertSame($first->loggers[0], $second->loggers[0]);
            self::assertSame(
                $first->loggers[0],
                $scope->get(CollectionFileLogger::class)
            );

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Collection attribute isolates different scopes
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionIsolatesDifferentScopes ():void {

        $container = new Container;

        $container->scoped(CollectionFileLogger::class);

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        try {

            $first = $firstScope->get(CollectionInjectionConsumer::class);
            $second = $secondScope->get(CollectionInjectionConsumer::class);

            self::assertNotSame($first->loggers[0], $second->loggers[0]);

        } finally {

            $firstScope->close();
            $secondScope->close();

        }

    }

    /**
     * ### Collection attribute supports factories
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionSupportsFactories ():void {

        $container = new Container;

        $executions = 0;

        $container->factory(
            CollectionFileLogger::class,
            static function ($resolver) use (&$executions):object {

                $executions++;

                return new CollectionFileLogger;

            }
        );

        self::assertSame(0, $executions);

        $consumer = $container->get(CollectionInjectionConsumer::class);

        self::assertInstanceOf(CollectionFileLogger::class, $consumer->loggers[0]);
        self::assertSame(1, $executions);

    }

    /**
     * ### Collection attribute sees registrations added after first resolution
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionSeesRegistrationsAddedAfterFirstResolution ():void {

        $container = new Container;

        self::assertSame(
            [],
            $container->get(CollectionInjectionConsumer::class)->loggers
        );

        $container->singleton(CollectionFileLogger::class);

        self::assertCount(
            1,
            $container->get(CollectionInjectionConsumer::class)->loggers
        );

    }

    /**
     * ### Collection attribute works after container freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionWorksAfterFreeze ():void {

        $container = new Container;

        $container->singleton(CollectionFileLogger::class);
        $container->freeze();

        self::assertCount(
            1,
            $container->get(CollectionInjectionConsumer::class)->loggers
        );

    }

    /**
     * ### Collection attribute preserves circular dependency detection
     * @since 1.0.0
     *
     * @return void
     */
    public function testInjectionPreservesCircularDependencyDetection ():void {

        $container = new Container;

        $container->transient(CollectionInjectionCircularB::class);

        $this->assertThrows(
            CircularDependencyException::class,
            static function () use ($container):void {

                $container->get(CollectionInjectionCircularA::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects nullable array
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsNullableArray ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionNullableConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects scalar types
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsScalar ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionScalarConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects untyped elements
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsUntyped ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionUntypedConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects unknown element types
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsUnknownElementType ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionUnknownTypeConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects empty element types
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsEmptyElementType ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionEmptyTypeConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects duplicate attributes
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsDuplicateAttributes ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionMultipleAttributesConsumer::class);

            }
        );

    }

    /**
     * ### Collection attribute rejects conflicting tagged and inject all
     * @since 1.0.0
     *
     * @return void
     */
    public function testRejectsConflictingTaggedAndInjectAll ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(CollectionInjectionConflictingAttributesConsumer::class);

            }
        );

    }

}