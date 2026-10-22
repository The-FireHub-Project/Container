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
use FireHub\Core\Meta\Enum\Container\Lifetime;
use FireHub\Container\Container;
use FireHub\Container\Exception\ServiceDisposalException;
use FireHub\Tests\Container\Stubs\{
    DisposableTestService, FailingDisposableTestService, FirstOrderedDisposableService, ResolvingDisposableTestService,
    SecondOrderedDisposableService
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests disposable service lifecycle
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[Group('lifecycle')]
#[Group('disposal')]
#[CoversClass(Container::class)]
final class DisposableServicesTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### Singleton disposable services are released on container closure
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingletonDisposedOnContainerClose ():void {

        $container = new Container;

        $container->singleton(DisposableTestService::class);

        $service = $container->get(DisposableTestService::class);

        self::assertSame(0, $service->disposals);

        $container->close();

        self::assertSame(1, $service->disposals);

    }

    /**
     * ### Singleton services are disposed of only once
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingletonDisposedOnlyOnce ():void {

        $container = new Container;

        $container->singleton(DisposableTestService::class);

        $service = $container->get(DisposableTestService::class);

        $container->close();
        $container->close();

        self::assertSame(1, $service->disposals);

    }

    /**
     * ### Unresolved singleton services are not disposed
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnresolvedSingletonIsNotInstantiatedDuringClose ():void {

        $container = new Container;

        $created = 0;

        $container->factory(
            DisposableTestService::class,
            function () use (&$created):DisposableTestService {

                $created++;

                return new DisposableTestService;

            },
            Lifetime::SINGLETON
        );

        $container->close();

        self::assertSame(0, $created);

    }

    /**
     * ### Scoped services are released on scope closure
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedServiceDisposedOnScopeClose ():void {

        $container = new Container;

        $container->scoped(DisposableTestService::class);

        $scope = $container->createScope();

        $service = $scope->get(DisposableTestService::class);

        self::assertSame(0, $service->disposals);

        $scope->close();

        self::assertSame(1, $service->disposals);

        $container->close();

        self::assertSame(1, $service->disposals);

    }

    /**
     * ### Independent scopes dispose of their own instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testIndependentScopesDisposeTheirOwnInstances ():void {

        $container = new Container;

        $container->scoped(DisposableTestService::class);

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        $first = $firstScope->get(DisposableTestService::class);
        $second = $secondScope->get(DisposableTestService::class);

        self::assertNotSame($first, $second);

        $firstScope->close();

        self::assertSame(1, $first->disposals);
        self::assertSame(0, $second->disposals);

        $secondScope->close();

        self::assertSame(1, $second->disposals);

        $container->close();

    }

    /**
     * ### Closing a scope does not dispose of singleton services
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeCloseDoesNotDisposeSingleton ():void {

        $container = new Container;

        $container->singleton(DisposableTestService::class);

        $scope = $container->createScope();

        $service = $scope->get(DisposableTestService::class);

        $scope->close();

        self::assertSame(0, $service->disposals);

        $container->close();

        self::assertSame(1, $service->disposals);

    }

    /**
     * ### Transient services are not automatically disposed
     * @since 1.0.0
     *
     * @return void
     */
    public function testTransientServicesAreNotAutomaticallyDisposed ():void {

        $container = new Container;

        $container->transient(DisposableTestService::class);

        $first = $container->get(DisposableTestService::class);
        $second = $container->get(DisposableTestService::class);

        self::assertNotSame($first, $second);

        $container->close();

        self::assertSame(0, $first->disposals);
        self::assertSame(0, $second->disposals);

    }

    /**
     * ### Externally supplied instances remain caller-owned
     * @since 1.0.0
     *
     * @return void
     */
    public function testExternalInstancesAreNotDisposed ():void {

        $container = new Container;

        $service = new DisposableTestService;

        $container->instance(
            DisposableTestService::class,
            $service
        );

        self::assertSame(
            $service,
            $container->get(DisposableTestService::class)
        );

        $container->close();

        self::assertSame(0, $service->disposals);

    }

    /**
     * ### Disposable singleton resources are released in reverse creation order
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingletonDisposalUsesReverseCreationOrder ():void {

        $container = new Container;

        $events = [];

        $container->factory(
            FirstOrderedDisposableService::class,
            function () use (&$events):FirstOrderedDisposableService {

                return new FirstOrderedDisposableService('first', $events);

            },
            Lifetime::SINGLETON
        );

        $container->factory(
            SecondOrderedDisposableService::class,
            function () use (&$events):SecondOrderedDisposableService {

                return new SecondOrderedDisposableService('second', $events);

            },
            Lifetime::SINGLETON
        );

        $container->get(FirstOrderedDisposableService::class);
        $container->get(SecondOrderedDisposableService::class);

        $container->close();

        self::assertSame(
            ['second', 'first'],
            $events
        );

    }

    /**
     * ### Disposal failures do not prevent container closure
     * @since 1.0.0
     *
     * @return void
     */
    public function testDisposalFailureDoesNotPreventContainerClosure ():void {

        $container = new Container;

        $container->singleton(FailingDisposableTestService::class);

        $service = $container->get(FailingDisposableTestService::class);

        $this->assertThrows(
            ServiceDisposalException::class,
            function () use ($container):void {

                $container->close();

            }
        );

        self::assertTrue($container->isClosed());
        self::assertSame(1, $service->disposals);

        $container->close();

        self::assertSame(1, $service->disposals);

    }

    /**
     * ### Disposal failures do not prevent remaining resources from being released
     * @since 1.0.0
     *
     * @return void
     */
    public function testDisposalContinuesAfterFailure ():void {

        $container = new Container;

        $container->singleton(DisposableTestService::class);
        $container->singleton(FailingDisposableTestService::class);

        $successful = $container->get(DisposableTestService::class);
        $failing = $container->get(FailingDisposableTestService::class);

        $this->assertThrows(
            ServiceDisposalException::class,
            function () use ($container):void {

                $container->close();

            }
        );

        self::assertSame(1, $successful->disposals);
        self::assertSame(1, $failing->disposals);

    }

    /**
     * ### Scoped disposal failures do not leave active scope registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeDisposalFailureUnregistersScope ():void {

        $container = new Container;

        $container->scoped(FailingDisposableTestService::class);

        $scope = $container->createScope();

        $scope->get(FailingDisposableTestService::class);

        $this->assertThrows(
            ServiceDisposalException::class,
            function () use ($scope):void {

                $scope->close();

            }
        );

        self::assertTrue($scope->isClosed());

        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Resolution is prohibited during container disposal
     * @since 1.0.0
     *
     * @return void
     */
    public function testResolutionDuringDisposalIsRejected ():void {

        $container = new Container;

        $service = new ResolvingDisposableTestService($container);

        $container->factory(
            ResolvingDisposableTestService::class,
            fn ():ResolvingDisposableTestService => $service,
            Lifetime::SINGLETON
        );

        $container->get(ResolvingDisposableTestService::class);

        $this->assertThrows(
            ServiceDisposalException::class,
            function () use ($container):void {

                $container->close();

            }
        );

        self::assertTrue($container->isClosed());

    }

}