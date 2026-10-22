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
    ContainerActiveScopesException, ContainerClosedException, ContainerFrozenException, ScopeActiveException,
    ScopeClosedException
};
use FireHub\Tests\Container\Stubs\Logger;
use FireHub\Tests\Container\Support\ExceptionAssertions;
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests dependency injection container lifecycle
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[Group('lifecycle')]
#[CoversClass(Container::class)]
final class ContainerLifecycleTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### New containers are open and mutable
     * @since 1.0.0
     *
     * @return void
     */
    public function testNewContainerIsOpenAndMutable ():void {

        $container = new Container;

        self::assertFalse($container->isFrozen());
        self::assertFalse($container->isClosed());

        $container->singleton(Logger::class);

        self::assertTrue($container->has(Logger::class));

    }

    /**
     * ### Freezing prevents further registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testFreezePreventsRegistrations ():void {

        $container = new Container;

        $container->freeze();

        self::assertTrue($container->isFrozen());
        self::assertFalse($container->isClosed());

        $this->assertThrows(
            ContainerFrozenException::class,
            function () use ($container):void {

                $container->singleton(Logger::class);

            }
        );

    }

    /**
     * ### Frozen containers continue resolving services
     * @since 1.0.0
     *
     * @return void
     */
    public function testFrozenContainerAllowsResolution ():void {

        $container = new Container;

        $container->singleton(Logger::class);

        $container->freeze();

        $first = $container->get(Logger::class);
        $second = $container->get(Logger::class);

        self::assertInstanceOf(Logger::class, $first);
        self::assertSame($first, $second);

    }

    /**
     * ### Freezing is idempotent
     * @since 1.0.0
     *
     * @return void
     */
    public function testFreezeIsIdempotent ():void {

        $container = new Container;

        $container->freeze();
        $container->freeze();

        self::assertTrue($container->isFrozen());

    }

    /**
     * ### Closing an open container changes its lifecycle state
     * @since 1.0.0
     *
     * @return void
     */
    public function testCloseChangesContainerState ():void {

        $container = new Container;

        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Closing is idempotent
     * @since 1.0.0
     *
     * @return void
     */
    public function testCloseIsIdempotent ():void {

        $container = new Container;

        $container->close();
        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Closed containers reject resolution
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosedContainerRejectsResolution ():void {

        $container = new Container;

        $container->singleton(Logger::class);

        $container->get(Logger::class);

        $container->close();

        $this->assertThrows(
            ContainerClosedException::class,
            function () use ($container):void {

                $container->get(Logger::class);

            }
        );

    }

    /**
     * ### Closed containers reject registrations
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosedContainerRejectsRegistrations ():void {

        $container = new Container;

        $container->close();

        $this->assertThrows(
            ContainerClosedException::class,
            function () use ($container):void {

                $container->singleton(Logger::class);

            }
        );

    }

    /**
     * ### Closed containers reject freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosedContainerRejectsFreeze ():void {

        $container = new Container;

        $container->close();

        $this->assertThrows(
            ContainerClosedException::class,
            function () use ($container):void {

                $container->freeze();

            }
        );

    }

    /**
     * ### Closed containers reject scope creation
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosedContainerRejectsScopeCreation ():void {

        $container = new Container;

        $container->close();

        $this->assertThrows(
            ContainerClosedException::class,
            function () use ($container):void {

                $container->createScope();

            }
        );

    }

    /**
     * ### Closed containers reject service queries
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosedContainerRejectsServiceQueries ():void {

        $container = new Container;

        $container->close();

        $this->assertThrows(
            ContainerClosedException::class,
            function () use ($container):void {

                $container->has(Logger::class);

            }
        );

    }

    /**
     * ### Frozen containers can be closed
     * @since 1.0.0
     *
     * @return void
     */
    public function testFrozenContainerCanBeClosed ():void {

        $container = new Container;

        $container->freeze();
        $container->close();

        self::assertTrue($container->isFrozen());
        self::assertTrue($container->isClosed());

    }

    /**
     * ### Active scopes prevent container closure
     * @since 1.0.0
     *
     * @return void
     */
    public function testActiveScopePreventsContainerClosure ():void {

        $container = new Container;

        $scope = $container->createScope();

        $this->assertThrows(
            ContainerActiveScopesException::class,
            function () use ($container):void {

                $container->close();

            }
        );

        self::assertFalse($container->isClosed());
        self::assertFalse($scope->isClosed());

        $scope->close();

        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Every active scope must close before its container
     * @since 1.0.0
     *
     * @return void
     */
    public function testMultipleScopesPreventContainerClosure ():void {

        $container = new Container;

        $first = $container->createScope();
        $second = $container->createScope();

        $first->close();

        $this->assertThrows(
            ContainerActiveScopesException::class,
            function () use ($container):void {

                $container->close();

            }
        );

        $second->close();

        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Closing a scope is idempotent
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeCloseIsIdempotent ():void {

        $container = new Container;

        $scope = $container->createScope();

        $scope->close();
        $scope->close();

        self::assertTrue($scope->isClosed());

        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Closed scopes reject resolution
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosedScopeRejectsResolution ():void {

        $container = new Container;

        $scope = $container->createScope();

        $scope->close();

        $this->assertThrows(
            ScopeClosedException::class,
            function () use ($scope):void {

                $scope->get(Logger::class);

            }
        );

    }

    /**
     * ### A scope cannot close while its run callback is active
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeCannotCloseDuringRun ():void {

        $container = new Container;

        $scope = $container->createScope();

        $scope->run(
            function () use ($scope):void {

                $this->assertThrows(
                    ScopeActiveException::class,
                    function () use ($scope):void {

                        $scope->close();

                    }
                );

            }
        );

        self::assertTrue($scope->isClosed());

        $container->close();

    }

    /**
     * ### Scope run automatically closes the scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeRunAutomaticallyClosesScope ():void {

        $container = new Container;

        $scope = $container->createScope();

        $executed = false;

        $scope->run(
            function () use (&$executed):void {

                $executed = true;

            }
        );

        self::assertTrue($executed);
        self::assertTrue($scope->isClosed());

        $container->close();

        self::assertTrue($container->isClosed());

    }

    /**
     * ### Scope run closes the scope when its callback fails
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopeRunClosesScopeOnException ():void {

        $container = new Container;

        $scope = $container->createScope();

        $this->assertThrows(
            \RuntimeException::class,
            function () use ($scope):void {

                $scope->run(
                    function ():void {

                        throw new \RuntimeException('Execution failed');

                    }
                );

            }
        );

        self::assertTrue($scope->isClosed());

        $container->close();

        self::assertTrue($container->isClosed());

    }

}