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
    AmbiguousDependencyException, ServiceInstantiationException, ScopeRequiredException, ServiceTypeMismatchException
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    DnfCache, DnfCacheService, DnfConsoleLogger, DnfDefaultConsumer, DnfFactoryConsumer, DnfFileLogger,
    DnfFirstBranchConsumer, DnfLogger, DnfMultipleNamedConsumer, DnfNestedConsumer, DnfNoCandidateConsumer,
    DnfRequiredNullableConsumer, DnfResettable, DnfScopedConsumer, DnfSecondBranchConsumer, DnfSingletonConsumer,
    DnfSerializableCache, DnfTwoIntersectionsConsumer, DnfUniversalService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests DNF and composite dependency resolution
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class DnfResolutionTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### DNF resolves the first intersection branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesTheFirstIntersectionBranch ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $service = $container->get(DnfFirstBranchConsumer::class);

        self::assertInstanceOf(DnfFileLogger::class, $service->dependency);
        self::assertInstanceOf(DnfLogger::class, $service->dependency);
        self::assertInstanceOf(DnfResettable::class, $service->dependency);

    }

    /**
     * ### DNF resolves the second named branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesTheSecondNamedBranch ():void {

        $container = new Container();

        $container->bind(
            DnfCacheService::class,
            DnfCacheService::class
        );

        $service = $container->get(DnfSecondBranchConsumer::class);

        self::assertInstanceOf(DnfCacheService::class, $service->dependency);

    }

    /**
     * ### DNF resolves the first of two intersection branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesTheFirstOfTwoIntersectionBranches ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $service = $container->get(DnfTwoIntersectionsConsumer::class);

        self::assertInstanceOf(DnfFileLogger::class, $service->dependency);

    }

    /**
     * ### DNF resolves the second of two intersection branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesTheSecondOfTwoIntersectionBranches ():void {

        $container = new Container();

        $container->bind(
            DnfSerializableCache::class,
            DnfSerializableCache::class
        );

        $service = $container->get(DnfTwoIntersectionsConsumer::class);

        self::assertInstanceOf(DnfSerializableCache::class, $service->dependency);

    }

    /**
     * ### DNF resolves a service satisfying multiple branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesAServiceSatisfyingMultipleBranches ():void {

        $container = new Container();

        $container->bind(
            DnfUniversalService::class,
            DnfUniversalService::class
        );

        $service = $container->get(DnfTwoIntersectionsConsumer::class);

        self::assertInstanceOf(DnfUniversalService::class, $service->dependency);

    }

    /**
     * ### DNF resolves a named alternative among multiple branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesANamedAlternativeAmongMultipleBranches ():void {

        $container = new Container();

        $container->bind(
            DnfCacheService::class,
            DnfCacheService::class
        );

        $service = $container->get(DnfMultipleNamedConsumer::class);

        self::assertInstanceOf(DnfCacheService::class, $service->dependency);

    }

    /**
     * ### DNF rejects multiple matching implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfRejectsMultipleMatchingImplementations ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $container->bind(
            DnfCacheService::class,
            DnfCacheService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(DnfFirstBranchConsumer::class);

            }
        );

    }

    /**
     * ### DNF rejects multiple implementations satisfying the same branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfRejectsMultipleImplementationsSatisfyingTheSameBranch ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $container->bind(
            DnfUniversalService::class,
            DnfUniversalService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(DnfFirstBranchConsumer::class);

            }
        );

    }

    /**
     * ### DNF rejects multiple implementations across intersection branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfRejectsMultipleImplementationsAcrossIntersectionBranches ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $container->bind(
            DnfSerializableCache::class,
            DnfSerializableCache::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(DnfTwoIntersectionsConsumer::class);

            }
        );

    }

    /**
     * ### DNF ignores implementations satisfying only part of an intersection
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfIgnoresImplementationsSatisfyingOnlyPartOfAnIntersection ():void {

        $container = new Container();

        $container->bind(
            DnfConsoleLogger::class,
            DnfConsoleLogger::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(DnfFirstBranchConsumer::class);

            }
        );

    }

    /**
     * ### DNF ignores implementations satisfying neither branch
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfIgnoresImplementationsSatisfyingNeitherBranch ():void {

        $container = new Container();

        $container->bind(
            DnfConsoleLogger::class,
            DnfConsoleLogger::class
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(DnfNoCandidateConsumer::class);

            }
        );

    }

    /**
     * ### DNF resolves a single candidate despite matching multiple branches
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesASingleCandidateDespiteMatchingMultipleBranches ():void {

        $container = new Container();

        $container->bind(
            DnfUniversalService::class,
            DnfUniversalService::class
        );

        $service = $container->get(DnfMultipleNamedConsumer::class);

        self::assertInstanceOf(DnfUniversalService::class, $service->dependency);

    }

    /**
     * ### DNF uses null default when no candidate exists
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfUsesNullDefaultWhenNoCandidateExists ():void {

        $service = new Container()->get(DnfDefaultConsumer::class);

        self::assertSame($service->dependency, null);

    }

    /**
     * ### DNF resolves a candidate before using null default
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesACandidateBeforeUsingNullDefault ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $service = $container->get(DnfDefaultConsumer::class);

        self::assertInstanceOf(DnfFileLogger::class, $service->dependency);

    }

    /**
     * ### DNF nullable dependency without default remains required
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfNullableDependencyWithoutDefaultRemainsRequired ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            function ():void {

                new Container()->get(DnfRequiredNullableConsumer::class);

            }
        );

    }

    /**
     * ### DNF nullable dependency resolves when candidate exists
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfNullableDependencyResolvesWhenCandidateExists ():void {

        $container = new Container();

        $container->bind(
            DnfCacheService::class,
            DnfCacheService::class
        );

        $service = $container->get(DnfRequiredNullableConsumer::class);

        self::assertInstanceOf(DnfCacheService::class, $service->dependency);

    }

    /**
     * ### DNF resolves dependencies inside nested graphs
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesDependenciesInsideNestedGraphs ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $service = $container->get(DnfNestedConsumer::class);

        self::assertInstanceOf(DnfFirstBranchConsumer::class, $service->consumer);
        self::assertInstanceOf(DnfFileLogger::class, $service->consumer->dependency);

    }

    /**
     * ### DNF dependencies remain transient by default
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfDependenciesRemainTransientByDefault ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $first = $container->get(DnfFirstBranchConsumer::class);
        $second = $container->get(DnfFirstBranchConsumer::class);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->dependency, $second->dependency);

    }

    /**
     * ### DNF reuses singleton dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfReusesSingletonDependencies ():void {

        $container = new Container();

        $container->singleton(DnfFileLogger::class);

        $first = $container->get(DnfFirstBranchConsumer::class);
        $second = $container->get(DnfFirstBranchConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);

    }

    /**
     * ### DNF singleton consumers retain their dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfSingletonConsumersRetainTheirDependencies ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $container->singleton(DnfSingletonConsumer::class);

        $first = $container->get(DnfSingletonConsumer::class);
        $second = $container->get(DnfSingletonConsumer::class);

        self::assertSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);

    }

    /**
     * ### DNF scoped dependencies require an active scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfScopedDependenciesRequireAnActiveScope ():void {

        $container = new Container();

        $container->scoped(DnfFileLogger::class);

        $this->assertThrows(
            ScopeRequiredException::class,
            function () use ($container):void {

                $container->get(DnfScopedConsumer::class);

            }
        );

    }

    /**
     * ### DNF scoped dependencies are reused within the same scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfScopedDependenciesAreReusedWithinTheSameScope ():void {

        $container = new Container();

        $container->scoped(DnfFileLogger::class);

        $scope = $container->createScope();

        $first = $scope->get(DnfScopedConsumer::class);
        $second = $scope->get(DnfScopedConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);

        $scope->close();

    }

    /**
     * ### DNF scoped dependencies are isolated between scopes
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfScopedDependenciesAreIsolatedBetweenScopes ():void {

        $container = new Container();

        $container->scoped(DnfFileLogger::class);

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        $first = $firstScope->get(DnfScopedConsumer::class);
        $second = $secondScope->get(DnfScopedConsumer::class);

        self::assertNotSame($first->dependency, $second->dependency);

        $firstScope->close();
        $secondScope->close();

    }

    /**
     * ### DNF resolves a factory registered under a named alternative
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfResolvesAFactoryRegisteredUnderANamedAlternative ():void {

        $container = new Container();

        $executed = false;

        $container->factory(
            DnfCache::class,
            function ($resolver) use (&$executed):object {

                $executed = true;

                return new DnfCacheService();

            }
        );

        $service = $container->get(DnfFactoryConsumer::class);

        self::assertTrue($executed);
        self::assertInstanceOf(DnfCacheService::class, $service->dependency);

    }

    /**
     * ### DNF does not execute factories during intersection candidate discovery
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfDoesNotExecuteFactoriesDuringIntersectionCandidateDiscovery ():void {

        $container = new Container();

        $executed = false;

        $container->factory(
            DnfLogger::class,
            function ($resolver) use (&$executed):object {

                $executed = true;

                return new DnfFileLogger();

            }
        );

        $this->assertThrows(
            ServiceInstantiationException::class,
            function () use ($container):void {

                $container->get(DnfFactoryConsumer::class);

            }
        );

        self::assertSame($executed, false);

    }

    /**
     * ### DNF rejects an incompatible factory result
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfRejectsAnIncompatibleFactoryResult ():void {

        $container = new Container();

        $container->factory(
            DnfCache::class,
            function ($resolver):object {

                return new DnfConsoleLogger();

            }
        );

        $this->assertThrows(
            ServiceTypeMismatchException::class,
            function () use ($container):void {

                $container->get(DnfFactoryConsumer::class);

            }
        );

    }

    /**
     * ### DNF dependency plans preserve transient semantics
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfDependencyPlansPreserveTransientSemantics ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $first = $container->get(DnfFirstBranchConsumer::class);
        $second = $container->get(DnfFirstBranchConsumer::class);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->dependency, $second->dependency);

    }

    /**
     * ### DNF dependency plans respect registrations added after first resolution
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfDependencyPlansRespectRegistrationsAddedAfterFirstResolution ():void {

        $container = new Container();

        $first = $container->get(DnfDefaultConsumer::class);

        self::assertNull($first->dependency);

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $second = $container->get(DnfDefaultConsumer::class);

        self::assertInstanceOf(DnfFileLogger::class, $second->dependency);

    }

    /**
     * ### DNF dependency plans detect new ambiguities after registration
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfDependencyPlansDetectNewAmbiguitiesAfterRegistration ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $first = $container->get(DnfFirstBranchConsumer::class);

        self::assertInstanceOf(DnfFileLogger::class, $first->dependency);

        $container->bind(
            DnfCacheService::class,
            DnfCacheService::class
        );

        $this->assertThrows(
            AmbiguousDependencyException::class,
            function () use ($container):void {

                $container->get(DnfFirstBranchConsumer::class);

            }
        );

    }

    /**
     * ### DNF autowiring works after freezing the container
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfAutowiringWorksAfterFreezingTheContainer ():void {

        $container = new Container();

        $container->bind(
            DnfFileLogger::class,
            DnfFileLogger::class
        );

        $container->freeze();

        $service = $container->get(DnfFirstBranchConsumer::class);

        self::assertInstanceOf(DnfFileLogger::class, $service->dependency);
        self::assertTrue($container->isFrozen());

    }

}
