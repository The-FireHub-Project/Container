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
    AmbiguousDependencyException, CircularDependencyException, ServiceInstantiationException, ScopeRequiredException
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    ContextualCache, ContextualCacheService, ContextualCircularA, ContextualCircularB, ContextualConsoleLogger,
    ContextualDefaultConsumer, ContextualDnfConsumer, ContextualFileLogger, ContextualIntersectionConsumer,
    ContextualLogger, ContextualNamedConsumer, ContextualNestedConsumer, ContextualOtherConsumer, ContextualResettable,
    ContextualScopedConsumer, ContextualSerializableCache, ContextualSingletonConsumer,
    ContextualTwoIntersectionsConsumer, ContextualUnionConsumer, ContextualUniversalService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests contextual dependency bindings
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class ContextualBindingTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### Contextual binding resolves a named dependency
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingResolvesANamedDependency ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualNamedConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->logger);

    }

    /**
     * ### Contextual bindings are isolated by consumer class
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingsAreIsolatedByConsumerClass ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(ContextualOtherConsumer::class);

            }
        );

    }

    /**
     * ### Different consumers resolve different contextual implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testDifferentConsumersResolveDifferentContextualImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualOtherConsumer::class,
            ContextualLogger::class,
            ContextualConsoleLogger::class
        );

        $first = $container->get(ContextualNamedConsumer::class);
        $second = $container->get(ContextualOtherConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $first->logger);
        self::assertInstanceOf(ContextualConsoleLogger::class, $second->logger);

    }

    /**
     * ### Contextual binding overrides global dependency registration
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingOverridesGlobalDependencyRegistration ():void {

        $container = new Container();

        $container->bind(
            ContextualLogger::class,
            ContextualConsoleLogger::class
        );

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualNamedConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->logger);

    }

    /**
     * ### Explicit parameter binding overrides contextual binding
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitParameterBindingOverridesContextualBinding ():void {

        $container = new Container();

        $logger = new ContextualConsoleLogger();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindParameters(
            ContextualNamedConsumer::class,
            [
                'logger' => $logger
            ]
        );

        $service = $container->get(ContextualNamedConsumer::class);

        self::assertSame($service->logger, $logger);

    }

    /**
     * ### Contextual binding resolves inside nested dependency graphs
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingResolvesInsideNestedDependencyGraphs ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualNestedConsumer::class);

        self::assertInstanceOf(ContextualNamedConsumer::class, $service->consumer);
        self::assertInstanceOf(ContextualFileLogger::class, $service->consumer->logger);

    }

    /**
     * ### Contextual union resolves the first alternative
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualUnionResolvesTheFirstAlternative ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualUnionConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->dependency);

    }

    /**
     * ### Contextual union resolves the second alternative
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualUnionResolvesTheSecondAlternative ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualCache::class,
            ContextualCacheService::class
        );

        $service = $container->get(ContextualUnionConsumer::class);

        self::assertInstanceOf(ContextualCacheService::class, $service->dependency);

    }

    /**
     * ### Contextual union rejects different implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualUnionRejectsDifferentImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualCache::class,
            ContextualCacheService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(ContextualUnionConsumer::class);

            }
        );

    }

    /**
     * ### Contextual union deduplicates identical implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualUnionDeduplicatesIdenticalImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualLogger::class,
            ContextualUniversalService::class
        );

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualCache::class,
            ContextualUniversalService::class
        );

        $service = $container->get(ContextualUnionConsumer::class);

        self::assertInstanceOf(ContextualUniversalService::class, $service->dependency);

    }

    /**
     * ### Contextual union takes precedence over standard candidates
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualUnionTakesPrecedenceOverStandardCandidates ():void {

        $container = new Container();

        $container->singleton(ContextualCacheService::class);

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualUnionConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->dependency);

    }

    /**
     * ### Contextual intersection resolves a compatible implementation
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualIntersectionResolvesACompatibleImplementation ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualIntersectionConsumer::class);

        self::assertInstanceOf(ContextualLogger::class, $service->dependency);
        self::assertInstanceOf(ContextualResettable::class, $service->dependency);

    }

    /**
     * ### Contextual intersection deduplicates identical implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualIntersectionDeduplicatesIdenticalImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualResettable::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualIntersectionConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->dependency);

    }

    /**
     * ### Contextual intersection rejects different implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualIntersectionRejectsDifferentImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualResettable::class,
            ContextualUniversalService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(ContextualIntersectionConsumer::class);

            }
        );

    }

    /**
     * ### Contextual intersection rejects incompatible implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualIntersectionRejectsIncompatibleImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualLogger::class,
            ContextualConsoleLogger::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(ContextualIntersectionConsumer::class);

            }
        );

    }

    /**
     * ### Invalid contextual intersection does not fall back to global candidates
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidContextualIntersectionDoesNotFallBackToGlobalCandidates ():void {

        $container = new Container();

        $container->bind(
            ContextualFileLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualLogger::class,
            ContextualConsoleLogger::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(ContextualIntersectionConsumer::class);

            }
        );

    }

    /**
     * ### Contextual DNF resolves an intersection branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDnfResolvesAnIntersectionBranch ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualDnfConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualDnfConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->dependency);

    }

    /**
     * ### Contextual DNF resolves a named branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDnfResolvesANamedBranch ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualDnfConsumer::class,
            ContextualCache::class,
            ContextualCacheService::class
        );

        $service = $container->get(ContextualDnfConsumer::class);

        self::assertInstanceOf(ContextualCacheService::class, $service->dependency);

    }

    /**
     * ### Contextual DNF resolves the second intersection branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDnfResolvesTheSecondIntersectionBranch ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualTwoIntersectionsConsumer::class,
            ContextualCache::class,
            ContextualSerializableCache::class
        );

        $service = $container->get(ContextualTwoIntersectionsConsumer::class);

        self::assertInstanceOf(ContextualSerializableCache::class, $service->dependency);

    }

    /**
     * ### Contextual DNF rejects multiple distinct implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDnfRejectsMultipleDistinctImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualDnfConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualDnfConsumer::class,
            ContextualCache::class,
            ContextualCacheService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(ContextualDnfConsumer::class);

            }
        );

    }

    /**
     * ### Contextual DNF deduplicates a universal implementation
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDnfDeduplicatesAUniversalImplementation ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualTwoIntersectionsConsumer::class,
            ContextualLogger::class,
            ContextualUniversalService::class
        );

        $container->bindContextual(
            ContextualTwoIntersectionsConsumer::class,
            ContextualCache::class,
            ContextualUniversalService::class
        );

        $service = $container->get(ContextualTwoIntersectionsConsumer::class);

        self::assertInstanceOf(ContextualUniversalService::class, $service->dependency);

    }

    /**
     * ### Contextual DNF rejects implementations incompatible with all branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDnfRejectsImplementationsIncompatibleWithAllBranches ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualTwoIntersectionsConsumer::class,
            ContextualLogger::class,
            ContextualConsoleLogger::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(ContextualTwoIntersectionsConsumer::class);

            }
        );

    }

    /**
     * ### Contextual dependencies are transient by default
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualDependenciesAreTransientByDefault ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $first = $container->get(ContextualNamedConsumer::class);
        $second = $container->get(ContextualNamedConsumer::class);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->logger, $second->logger);

    }

    /**
     * ### Contextual bindings preserve singleton lifetimes
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingsPreserveSingletonLifetimes ():void {

        $container = new Container();

        $container->singleton(ContextualFileLogger::class);

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $first = $container->get(ContextualNamedConsumer::class);
        $second = $container->get(ContextualNamedConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->logger, $second->logger);

    }

    /**
     * ### Contextual scoped dependencies require an active scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualScopedDependenciesRequireAnActiveScope ():void {

        $container = new Container();

        $container->scoped(ContextualFileLogger::class);

        $container->bindContextual(
            ContextualScopedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $this->assertThrows(
            ScopeRequiredException::class,
            function () use ($container):void {

                $container->get(ContextualScopedConsumer::class);

            }
        );

    }

    /**
     * ### Contextual scoped dependencies are reused within a scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualScopedDependenciesAreReusedWithinAScope ():void {

        $container = new Container();

        $container->scoped(ContextualFileLogger::class);

        $container->bindContextual(
            ContextualScopedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $scope = $container->createScope();

        $first = $scope->get(ContextualScopedConsumer::class);
        $second = $scope->get(ContextualScopedConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->logger, $second->logger);

        $scope->close();

    }

    /**
     * ### Contextual scoped dependencies are isolated between scopes
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualScopedDependenciesAreIsolatedBetweenScopes ():void {

        $container = new Container();

        $container->scoped(ContextualFileLogger::class);

        $container->bindContextual(
            ContextualScopedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        $first = $firstScope->get(ContextualScopedConsumer::class);
        $second = $secondScope->get(ContextualScopedConsumer::class);

        self::assertNotSame($first->logger, $second->logger);

        $firstScope->close();
        $secondScope->close();

    }

    /**
     * ### Contextual singleton consumers retain their dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualSingletonConsumersRetainTheirDependencies ():void {

        $container = new Container();

        $container->singleton(ContextualSingletonConsumer::class);

        $container->bindContextual(
            ContextualSingletonConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $first = $container->get(ContextualSingletonConsumer::class);
        $second = $container->get(ContextualSingletonConsumer::class);

        self::assertSame($first, $second);
        self::assertSame($first->logger, $second->logger);

    }

    /**
     * ### Contextual binding overrides a nullable default
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingOverridesANullableDefault ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualDefaultConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $service = $container->get(ContextualDefaultConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->logger);

    }

    /**
     * ### Contextual bindings added after resolution are recognized
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingsAddedAfterResolutionAreRecognized ():void {

        $container = new Container();

        $first = $container->get(ContextualDefaultConsumer::class);

        self::assertTrue($first->logger === null);

        $container->bindContextual(
            ContextualDefaultConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $second = $container->get(ContextualDefaultConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $second->logger);

    }

    /**
     * ### Contextual union detects ambiguity after new registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualUnionDetectsAmbiguityAfterNewRegistrations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $first = $container->get(ContextualUnionConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $first->dependency);

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualCache::class,
            ContextualCacheService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(ContextualUnionConsumer::class);

            }
        );

    }

    /**
     * ### Duplicate contextual bindings are rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testDuplicateContextualBindingsAreRejected ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $this->assertThrows(
            \FireHub\Container\Exception\DuplicateContextualBindingException::class,
            function () use ($container):void {

                $container->bindContextual(
                    ContextualNamedConsumer::class,
                    ContextualLogger::class,
                    ContextualConsoleLogger::class
                );

            }
        );

    }

    /**
     * ### Contextual bindings cannot be registered after freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingsCannotBeRegisteredAfterFreezing ():void {

        $container = new Container();

        $container->freeze();

        $this->assertThrows(
            \FireHub\Container\Exception\ContainerFrozenException::class,
            function () use ($container):void {

                $container->bindContextual(
                    ContextualNamedConsumer::class,
                    ContextualLogger::class,
                    ContextualFileLogger::class
                );

            }
        );

    }

    /**
     * ### Contextual bindings resolve after freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingsResolveAfterFreezing ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->freeze();

        $service = $container->get(ContextualNamedConsumer::class);

        self::assertInstanceOf(ContextualFileLogger::class, $service->logger);
        self::assertTrue($container->isFrozen());

    }

    /**
     * ### Contextual resolution preserves circular dependency detection
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualResolutionPreservesCircularDependencyDetection ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualCircularA::class,
            ContextualCircularB::class,
            ContextualCircularB::class
        );

        $this->assertThrows(
            CircularDependencyException::class,
            function () use ($container):void {

                $container->get(ContextualCircularA::class);

            }
        );

    }

    /**
     * ### Contextual named binding rejects incompatible implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualNamedBindingRejectsIncompatibleImplementations ():void {

        $container = new Container();

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualCacheService::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(ContextualNamedConsumer::class);

            }
        );

    }

}
