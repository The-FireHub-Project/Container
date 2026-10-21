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
    CircularDependencyException, ServiceInstantiationException, ScopeRequiredException
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    TaggedConsoleLogger, TaggedFileLogger, TaggedInjectionCircularA, TaggedInjectionCircularB, TaggedInjectionConsumer,
    TaggedInjectionDefaultConsumer, TaggedInjectionEmptyTagConsumer, TaggedInjectionNestedConsumer,
    TaggedInjectionNullableConsumer, TaggedInjectionScalarConsumer, TaggedInjectionUnknownConsumer,
    TaggedInjectionUntypedConsumer, TaggedInjectionWhitespaceTagConsumer, TaggedLogger
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests tagged constructor injection
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class TaggedInjectionTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### Tagged attribute injects services in registration order
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedAttributeInjectsServicesInRegistrationOrder ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->singleton(TaggedConsoleLogger::class);

        $container->tag(
            'logging',
            TaggedConsoleLogger::class,
            TaggedFileLogger::class
        );

        $consumer = $container->get(TaggedInjectionConsumer::class);

        self::assertCount(2, $consumer->loggers);
        self::assertInstanceOf(TaggedConsoleLogger::class, $consumer->loggers[0]);
        self::assertInstanceOf(TaggedFileLogger::class, $consumer->loggers[1]);

    }

    /**
     * ### Tagged attribute injects an empty array for unknown tags
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedAttributeInjectsAnEmptyArrayForUnknownTags ():void {

        $consumer = (new Container)->get(TaggedInjectionUnknownConsumer::class);

        self::assertSame([], $consumer->services);

    }

    /**
     * ### Tagged attribute overrides an array constructor default
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedAttributeOverridesAnArrayConstructorDefault ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $consumer = $container->get(TaggedInjectionDefaultConsumer::class);

        self::assertCount(1, $consumer->loggers);
        self::assertInstanceOf(TaggedFileLogger::class, $consumer->loggers[0]);

    }

    /**
     * ### Tagged attribute works in nested dependency graphs
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedAttributeWorksInNestedDependencyGraphs ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $nested = $container->get(TaggedInjectionNestedConsumer::class);

        self::assertInstanceOf(TaggedInjectionConsumer::class, $nested->consumer);
        self::assertInstanceOf(TaggedFileLogger::class, $nested->consumer->loggers[0]);

    }

    /**
     * ### Explicit parameter bindings override tagged injection
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitParameterBindingsOverrideTaggedInjection ():void {

        $container = new Container;

        $override = [new TaggedConsoleLogger];

        $container->bindParameters(
            TaggedInjectionConsumer::class,
            ['loggers' => $override]
        );

        $consumer = $container->get(TaggedInjectionConsumer::class);

        self::assertSame($consumer->loggers, $override);

    }

    /**
     * ### Tagged injection uses singleton instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionUsesSingletonInstances ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $first = $container->get(TaggedInjectionConsumer::class);
        $second = $container->get(TaggedInjectionConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->loggers[0], $second->loggers[0]);
        self::assertSame($first->loggers[0], $container->get(TaggedFileLogger::class));

    }

    /**
     * ### Tagged injection creates new transient instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionCreatesNewTransientInstances ():void {

        $container = new Container;

        $container->transient(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $first = $container->get(TaggedInjectionConsumer::class);
        $second = $container->get(TaggedInjectionConsumer::class);

        self::assertNotSame($first->loggers[0], $second->loggers[0]);

    }

    /**
     * ### Tagged injection requires a scope for scoped services
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRequiresAScopeForScopedServices ():void {

        $container = new Container;

        $container->scoped(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $this->assertThrows(
            ScopeRequiredException::class,
            function () use ($container):void {

                $container->get(TaggedInjectionConsumer::class);

            }
        );

    }

    /**
     * ### Tagged injection reuses scoped services within a scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionReusesScopedServicesWithinAScope ():void {

        $container = new Container;

        $container->scoped(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $scope = $container->createScope();

        try {

            $first = $scope->get(TaggedInjectionConsumer::class);
            $second = $scope->get(TaggedInjectionConsumer::class);

            self::assertNotSame($first, $second);
            self::assertSame($first->loggers[0], $second->loggers[0]);
            self::assertSame($first->loggers[0], $scope->get(TaggedFileLogger::class));

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Tagged injection isolates scoped services between scopes
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionIsolatesScopedServicesBetweenScopes ():void {

        $container = new Container;

        $container->scoped(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        try {

            $first = $firstScope->get(TaggedInjectionConsumer::class);
            $second = $secondScope->get(TaggedInjectionConsumer::class);

            self::assertTrue($first->loggers[0] !== $second->loggers[0]);

        } finally {

            $firstScope->close();
            $secondScope->close();

        }

    }

    /**
     * ### Tagged injection uses the active scope in nested graphs
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionUsesTheActiveScopeInNestedGraphs ():void {

        $container = new Container;

        $container->scoped(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $scope = $container->createScope();

        try {

            $nested = $scope->get(TaggedInjectionNestedConsumer::class);

            self::assertSame($nested->consumer->loggers[0], $scope->get(TaggedFileLogger::class));

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Tagged injection resolves aliases
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionResolvesAliases ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->alias(TaggedLogger::class, TaggedFileLogger::class);
        $container->tag('logging', TaggedLogger::class);

        $consumer = $container->get(TaggedInjectionConsumer::class);

        self::assertCount(1, $consumer->loggers);
        self::assertSame($consumer->loggers[0], $container->get(TaggedFileLogger::class));

    }

    /**
     * ### Tagged injection resolves factories
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionResolvesFactories ():void {

        $container = new Container;

        $executions = 0;

        $container->factory(
            TaggedFileLogger::class,
            static function ($resolver) use (&$executions):object {

                $executions++;

                return new TaggedFileLogger;

            }
        );

        $container->tag('logging', TaggedFileLogger::class);

        self::assertSame($executions, 0);

        $consumer = $container->get(TaggedInjectionConsumer::class);

        self::assertInstanceOf(TaggedFileLogger::class, $consumer->loggers[0]);
        self::assertSame($executions, 1);

    }

    /**
     * ### Tagged injection respects dynamically added tag associations
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRespectsDynamicallyAddedTagAssociations ():void {

        $container = new Container;

        $first = $container->get(TaggedInjectionConsumer::class);

        self::assertSame($first->loggers, []);

        $container->singleton(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);

        $second = $container->get(TaggedInjectionConsumer::class);

        self::assertCount(1, $second->loggers);
        self::assertInstanceOf(TaggedFileLogger::class, $second->loggers[0]);

    }

    /**
     * ### Tagged injection works after container freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionWorksAfterContainerFreezing ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->tag('logging', TaggedFileLogger::class);
        $container->freeze();

        $consumer = $container->get(TaggedInjectionConsumer::class);

        self::assertInstanceOf(TaggedFileLogger::class, $consumer->loggers[0]);

    }

    /**
     * ### Tagged injection rejects nullable array parameters
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRejectsNullableArrayParameters ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(TaggedInjectionNullableConsumer::class);

            }
        );

    }

    /**
     * ### Tagged injection rejects scalar parameters
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRejectsScalarParameters ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(TaggedInjectionScalarConsumer::class);

            }
        );

    }

    /**
     * ### Tagged injection rejects untyped parameters
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRejectsUntypedParameters ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(TaggedInjectionUntypedConsumer::class);

            }
        );

    }

    /**
     * ### Tagged injection rejects empty tag names
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRejectsEmptyTagNames ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(TaggedInjectionEmptyTagConsumer::class);

            }
        );

    }

    /**
     * ### Tagged injection rejects whitespace-only tag names
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionRejectsWhitespaceOnlyTagNames ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {

                (new Container)->get(TaggedInjectionWhitespaceTagConsumer::class);

            }
        );

    }

    /**
     * ### Tagged injection preserves circular dependency detection
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedInjectionPreservesCircularDependencyDetection ():void {

        $container = new Container;

        $container->transient(TaggedInjectionCircularB::class);
        $container->tag('circular', TaggedInjectionCircularB::class);

        $this->assertThrows(
            CircularDependencyException::class,
            function () use ($container):void {

                $container->get(TaggedInjectionCircularA::class);

            }
        );

    }

}
