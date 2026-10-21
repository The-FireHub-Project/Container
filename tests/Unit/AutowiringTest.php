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
    AmbiguousDependencyException, CircularDependencyException, ServiceInstantiationException,
    ServiceNotFoundException, ScopeRequiredException
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    AbstractLogger, AnotherTestLogger, CircularA, IntersectionConsumer, Logger, LoggerInterface, MixedDefault,
    MixedRequired, NullableConcrete, NullableDefault, NullableRequired, Repository, ScalarDefault, ScalarRequired,
    ScopedConsumer, ScopedDependency, SingletonConsumer, TestFileLogger, TestLogger, UnionA, UnionAmbiguous, UnionB,
    UnionInterface, UnionNullable, UnionOnlyA, UserService, VariadicService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests constructor dependency autowiring
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class AutowiringTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### Implicit autowiring resolves nested dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testImplicitAutowiringResolvesNestedDependencies ():void {

        $container = new Container();

        $service = $container->get(UserService::class);

        self::assertInstanceOf(UserService::class, $service);
        self::assertInstanceOf(Repository::class, $service->repository);
        self::assertInstanceOf(Logger::class, $service->repository->logger);

    }

    /**
     * ### Implicit services are transient
     * @since 1.0.0
     *
     * @return void
     */
    public function testImplicitServicesAreTransient ():void {

        $container = new Container();

        $first = $container->get(UserService::class);
        $second = $container->get(UserService::class);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->repository, $second->repository);
        self::assertNotSame($first->repository->logger, $second->repository->logger);

    }

    /**
     * ### Implicit resolution does not register services
     * @since 1.0.0
     *
     * @return void
     */
    public function testImplicitResolutionDoesNotRegisterServices ():void {

        $container = new Container();

        self::assertNotTrue($container->has(UserService::class));

        $container->get(UserService::class);

        self::assertNotTrue($container->has(UserService::class));

    }

    /**
     * ### Unregistered interfaces cannot be instantiated
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnregisteredInterfacesCannotBeInstantiated ():void {

        $this->assertThrows(ServiceNotFoundException::class, function ():void {

            new Container()->get(LoggerInterface::class);

        });

    }

    /**
     * ### Abstract classes cannot be instantiated
     * @since 1.0.0
     *
     * @return void
     */
    public function testAbstractClassesCannotBeInstantiated ():void {

        $this->assertThrows(ServiceNotFoundException::class, function ():void {

            new Container()->get(AbstractLogger::class);

        });

    }

    /**
     * ### Required scalar parameters fail
     * @since 1.0.0
     *
     * @return void
     */
    public function testRequiredScalarParametersFail ():void {

        $this->assertThrows(ServiceInstantiationException::class, function ():void {

            new Container()->get(ScalarRequired::class);

        });

    }

    /**
     * ### Scalar parameters use default values
     * @since 1.0.0
     *
     * @return void
     */
    public function testScalarParametersUseDefaultValues ():void {

        $service = new Container()->get(ScalarDefault::class);

        self::assertSame(8080, $service->port);
        self::assertSame('localhost', $service->host);

    }

    /**
     * ### Nullable dependencies without defaults remain required
     * @since 1.0.0
     *
     * @return void
     */
    public function testNullableDependenciesWithoutDefaultsRemainRequired ():void {

        $this->assertThrows(ServiceInstantiationException::class, function ():void {

            new Container()->get(NullableRequired::class);

        });

    }

    /**
     * ### Nullable dependencies use null defaults
     * @since 1.0.0
     *
     * @return void
     */
    public function testNullableDependenciesUseNullDefaults ():void {

        $service = new Container()->get(NullableDefault::class);

        self::assertNull($service->logger);

    }

    /**
     * ### Nullable concrete dependencies are autowired
     * @since 1.0.0
     *
     * @return void
     */
    public function testNullableConcreteDependenciesAreAutowired ():void {

        $service = new Container()->get(NullableConcrete::class);

        self::assertInstanceOf(Logger::class, $service->logger);

    }

    /**
     * ### Variadic parameters accept zero dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testVariadicParametersAcceptZeroDependencies ():void {

        $service = new Container()->get(VariadicService::class);

        self::assertSame($service->loggers, []);

    }

    /**
     * ### Required mixed parameters fail
     * @since 1.0.0
     *
     * @return void
     */
    public function testRequiredMixedParametersFail ():void {

        $this->assertThrows(ServiceInstantiationException::class, function ():void {

            new Container()->get(MixedRequired::class);

        });

    }

    /**
     * ### Mixed parameters use defaults
     * @since 1.0.0
     *
     * @return void
     */
    public function testMixedParametersUseDefaults ():void {

        $service = new Container()->get(MixedDefault::class);

        self::assertNull($service->value);

    }

    /**
     * ### Circular dependencies are detected
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularDependenciesAreDetected ():void {

        $this->assertThrows(CircularDependencyException::class, function ():void {

            new Container()->get(CircularA::class);

        });

    }

    /**
     * ### Explicit singletons are reused inside implicit graphs
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitSingletonsAreReusedInsideImplicitGraphs ():void {

        $container = new Container();

        $container->singleton(Logger::class);

        $first = $container->get(UserService::class);
        $second = $container->get(UserService::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->repository->logger, $second->repository->logger);

    }

    /**
     * ### Explicit singleton consumers retain transient dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitSingletonConsumersRetainTransientDependencies ():void {

        $container = new Container();

        $container->singleton(SingletonConsumer::class);

        $first = $container->get(SingletonConsumer::class);
        $second = $container->get(SingletonConsumer::class);

        self::assertSame($first, $second);
        self::assertSame($first->logger, $second->logger);

    }

    /**
     * ### Scoped dependencies require an active scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedDependenciesRequireAnActiveScope ():void {

        $container = new Container();

        $container->scoped(ScopedDependency::class);

        $this->assertThrows(ScopeRequiredException::class, function () use ($container):void {

            $container->get(ScopedConsumer::class);

        });

    }

    /**
     * ### Scoped dependencies resolve within a scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedDependenciesResolveWithinAScope ():void {

        $container = new Container();

        $container->scoped(ScopedDependency::class);

        $scope = $container->createScope();

        $first = $scope->get(ScopedConsumer::class);
        $second = $scope->get(ScopedConsumer::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);

        $scope->close();

    }

    /**
     * ### Implicit autowiring works after freezing the container
     * @since 1.0.0
     *
     * @return void
     */
    public function testImplicitAutowiringWorksAfterFreezingTheContainer ():void {

        $container = new Container();

        $container->freeze();

        $service = $container->get(UserService::class);

        self::assertInstanceOf(UserService::class, $service);
        self::assertTrue($container->isFrozen());

    }

    /**
     * ### Union resolves a single implicit candidate
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionResolvesASingleImplicitCandidate ():void {

        $service = new Container()->get(UnionOnlyA::class);

        self::assertInstanceOf(UnionA::class, $service->dependency);

    }

    /**
     * ### Union rejects multiple implicit candidates
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionRejectsMultipleImplicitCandidates ():void {

        $this->assertThrows(AmbiguousDependencyException::class, function ():void {

            new Container()->get(UnionAmbiguous::class);

        });

    }

    /**
     * ### Union prefers an explicit registration
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionPrefersAnExplicitRegistration ():void {

        $container = new Container();

        $container->singleton(UnionB::class);

        $service = $container->get(UnionAmbiguous::class);

        self::assertInstanceOf(UnionB::class, $service->dependency);

    }

    /**
     * ### Union rejects multiple explicit registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionRejectsMultipleExplicitRegistrations ():void {

        $container = new Container();

        $container->singleton(UnionA::class);
        $container->singleton(UnionB::class);

        $this->assertThrows(AmbiguousDependencyException::class, function () use ($container):void {

            $container->get(UnionAmbiguous::class);

        });

    }

    /**
     * ### Union resolves an implicit candidate before using null default
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionResolvesAnImplicitCandidateBeforeUsingNullDefault ():void {

        $service = new Container()->get(UnionNullable::class);

        self::assertInstanceOf(UnionB::class, $service->dependency);

    }

    /**
     * ### Intersection resolves a unique registered implementation
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionResolvesAUniqueRegisteredImplementation ():void {

        $container = new Container();

        $container->bind(
            TestLogger::class,
            TestFileLogger::class
        );

        $service = $container->get(IntersectionConsumer::class);

        self::assertInstanceOf(TestFileLogger::class, $service->logger);

    }

    /**
     * ### Intersection fails without matching implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionFailsWithoutMatchingImplementations ():void {

        $this->assertThrows(ServiceInstantiationException::class, function ():void {

            new Container()->get(IntersectionConsumer::class);

        });

    }

    /**
     * ### Intersection rejects multiple matching implementations
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionRejectsMultipleMatchingImplementations ():void {

        $container = new Container();

        $container->bind(TestFileLogger::class, TestFileLogger::class);
        $container->bind(AnotherTestLogger::class, AnotherTestLogger::class);

        $this->assertThrows(AmbiguousDependencyException::class, function () use ($container):void {

            $container->get(IntersectionConsumer::class);

        });

    }

    /**
     * ### Intersection does not execute factories during candidate discovery
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionDoesNotExecuteFactoriesDuringCandidateDiscovery ():void {

        $container = new Container();

        $executed = false;

        $container->factory(
            TestLogger::class,
            function ($resolver) use (&$executed):object {

                $executed = true;

                return new TestFileLogger();

            }
        );

        $this->assertThrows(ServiceInstantiationException::class, function () use ($container):void {

            $container->get(IntersectionConsumer::class);

        });

        self::assertFalse($executed);

    }

    /**
     * ### Metadata caching preserves transient dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testMetadataCachingPreservesTransientDependencies ():void {

        $container = new Container();

        $first = $container->get(UserService::class);
        $second = $container->get(UserService::class);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->repository, $second->repository);
        self::assertNotSame($first->repository->logger, $second->repository->logger);

    }

    /**
     * ### Metadata caching respects new registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testMetadataCachingRespectsNewRegistrations ():void {

        $container = new Container();

        $first = $container->get(UnionOnlyA::class);

        self::assertInstanceOf(UnionA::class, $first->dependency);

        $container->singleton(UnionInterface::class, UnionA::class);

    }

}
