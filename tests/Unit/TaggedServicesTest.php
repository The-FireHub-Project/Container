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
    ServiceNotFoundException, ScopeRequiredException
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use FireHub\Tests\Container\Stubs\ {
    TaggedConsoleLogger, TaggedDatabaseLogger, TaggedFactoryService, TaggedFileLogger, TaggedLogger, TaggedScopedService,
    TaggedSingletonService, TaggedTransientService, TaggedUnregisteredService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests tagged services
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class TaggedServicesTest extends FireHubTestCase {

    use ExceptionAssertions;

    /**
     * ### Tags can be registered for explicit services
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagsCanBeRegisteredForExplicitServices ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->singleton(TaggedConsoleLogger::class);

        $container->tag(
            'logging',
            TaggedFileLogger::class,
            TaggedConsoleLogger::class
        );

        self::assertTrue($container->hasTag('logging'));

        self::assertSame(
            [
                TaggedFileLogger::class,
                TaggedConsoleLogger::class
            ], $container->taggedIds('logging')
        );

    }

    /**
     * ### Unknown tags return empty collections
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnknownTagsReturnEmptyCollections ():void {

        $container = new Container;

        self::assertNotTrue($container->hasTag('unknown'));
        self::assertSame([], $container->taggedIds('unknown'));
        self::assertSame([], $container->tagged('unknown'));

        $scope = $container->createScope();

        self::assertSame([], $scope->tagged('unknown'));

        $scope->close();

    }

    /**
     * ### Tagged services preserve association order
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedServicesPreserveAssociationOrder ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->singleton(TaggedConsoleLogger::class);
        $container->singleton(TaggedDatabaseLogger::class);

        $container->tag(
            'logging',
            TaggedDatabaseLogger::class,
            TaggedFileLogger::class,
            TaggedConsoleLogger::class
        );

        self::assertSame(
            [
                TaggedDatabaseLogger::class,
                TaggedFileLogger::class,
                TaggedConsoleLogger::class
            ], $container->taggedIds('logging')
        );

        $services = $container->tagged('logging');

        self::assertCount(3, $services);
        self::assertInstanceOf(TaggedDatabaseLogger::class, $services[0]);
        self::assertInstanceOf(TaggedFileLogger::class, $services[1]);
        self::assertInstanceOf(TaggedConsoleLogger::class, $services[2]);

    }

    /**
     * ### Tags support multiple registration calls
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagsSupportMultipleRegistrationCalls ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->singleton(TaggedConsoleLogger::class);

        $container->tag('logging', TaggedFileLogger::class);
        $container->tag('logging', TaggedConsoleLogger::class);

        self::assertSame(
            [
                TaggedFileLogger::class,
                TaggedConsoleLogger::class
            ], $container->taggedIds('logging')
        );

    }

    /**
     * ### Duplicate tagged services are ignored
     * @since 1.0.0
     *
     * @return void
     */
    public function testDuplicateTaggedServicesAreIgnored ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $container->tag(
            'logging',
            TaggedFileLogger::class,
            TaggedFileLogger::class
        );

        $container->tag('logging', TaggedFileLogger::class);

        self::assertSame(
            [
                TaggedFileLogger::class
            ], $container->taggedIds('logging')
        );

        self::assertCount(1, $container->tagged('logging'));

    }

    /**
     * ### Services can belong to multiple tags
     * @since 1.0.0
     *
     * @return void
     */
    public function testServicesCanBelongToMultipleTags ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $container->tag('logging', TaggedFileLogger::class);
        $container->tag('monitoring', TaggedFileLogger::class);

        self::assertTrue($container->hasTag('logging'));
        self::assertTrue($container->hasTag('monitoring'));

        $logging = $container->tagged('logging');
        $monitoring = $container->tagged('monitoring');

        self::assertSame($logging[0], $monitoring[0]);

    }

    /**
     * ### Tag registration does not instantiate services
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagRegistrationDoesNotInstantiateServices ():void {

        $container = new Container;

        $executed = false;

        $container->factory(
            TaggedFactoryService::class,
            function ($resolver) use (&$executed):object {

                $executed = true;

                return new TaggedFactoryService;

            }
        );

        $container->tag('services', TaggedFactoryService::class);

        self::assertFalse($executed);

        $container->hasTag('services');
        $container->taggedIds('services');

        self::assertFalse($executed);

        $container->tagged('services');

        self::assertTrue($executed);

    }

    /**
     * ### Tag registration requires explicitly registered services
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagRegistrationRequiresExplicitlyRegisteredServices ():void {

        $container = new Container;

        $this->assertThrows(
            ServiceNotFoundException::class,
            function () use ($container):void {

                $container->tag(
                    'services',
                    TaggedUnregisteredService::class
                );

            }
        );

        self::assertNotTrue($container->hasTag('services'));

    }

    /**
     * ### Tag registration is atomic
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagRegistrationIsAtomic ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $this->assertThrows(
            ServiceNotFoundException::class,
            function () use ($container):void {

                $container->tag(
                    'logging',
                    TaggedFileLogger::class,
                    TaggedUnregisteredService::class
                );

            }
        );

        self::assertNotTrue($container->hasTag('logging'));
        self::assertSame([], $container->taggedIds('logging'));

    }

    /**
     * ### Failed tag registration preserves existing associations
     * @since 1.0.0
     *
     * @return void
     */
    public function testFailedTagRegistrationPreservesExistingAssociations ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);
        $container->singleton(TaggedConsoleLogger::class);

        $container->tag('logging', TaggedFileLogger::class);

        $this->assertThrows(
            ServiceNotFoundException::class,
            function () use ($container):void {

                $container->tag(
                    'logging',
                    TaggedConsoleLogger::class,
                    TaggedUnregisteredService::class
                );

            }
        );

        self::assertSame(
            [
                TaggedFileLogger::class
            ], $container->taggedIds('logging')
        );

    }

    /**
     * ### Empty tag names are rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testEmptyTagNamesAreRejected ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $this->assertThrows(
            \FireHub\Container\Exception\InvalidTagNameException::class,
            function () use ($container):void {

                $container->tag('', TaggedFileLogger::class);

            }
        );

    }

    /**
     * ### Whitespace-only tag names are rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testWhitespaceOnlyTagNamesAreRejected ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $this->assertThrows(
            \FireHub\Container\Exception\InvalidTagNameException::class,
            function () use ($container):void {

                $container->tag('   ', TaggedFileLogger::class);

            }
        );

    }

    /**
     * ### Tagged singleton services are reused
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedSingletonServicesAreReused ():void {

        $container = new Container;

        $container->singleton(TaggedSingletonService::class);

        $container->tag('services', TaggedSingletonService::class);

        $first = $container->tagged('services');
        $second = $container->tagged('services');

        self::assertCount(1, $first);
        self::assertSame($first[0], $second[0]);
        self::assertSame($first[0], $container->get(TaggedSingletonService::class));

    }

    /**
     * ### Tagged transient services are recreated
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedTransientServicesAreRecreated ():void {

        $container = new Container;

        $container->transient(TaggedTransientService::class);

        $container->tag('services', TaggedTransientService::class);

        $first = $container->tagged('services');
        $second = $container->tagged('services');

        self::assertInstanceOf(TaggedTransientService::class, $first[0]);
        self::assertInstanceOf(TaggedTransientService::class, $second[0]);
        self::assertNotSame($first[0], $second[0]);

    }

    /**
     * ### Tagged scoped services require an active scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedScopedServicesRequireAnActiveScope ():void {

        $container = new Container;

        $container->scoped(TaggedScopedService::class);

        $container->tag('services', TaggedScopedService::class);

        $this->assertThrows(
            ScopeRequiredException::class,
            function () use ($container):void {

                $container->tagged('services');

            }
        );

    }

    /**
     * ### Tagged scoped services are reused within the same scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedScopedServicesAreReusedWithinTheSameScope ():void {

        $container = new Container;

        $container->scoped(TaggedScopedService::class);

        $container->tag('services', TaggedScopedService::class);

        $scope = $container->createScope();

        $first = $scope->tagged('services');
        $second = $scope->tagged('services');

        self::assertInstanceOf(TaggedScopedService::class, $first[0]);
        self::assertSame($first[0], $second[0]);
        self::assertSame($first[0], $scope->get(TaggedScopedService::class));

        $scope->close();

    }

    /**
     * ### Tagged scoped services are isolated between scopes
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedScopedServicesAreIsolatedBetweenScopes ():void {

        $container = new Container;

        $container->scoped(TaggedScopedService::class);

        $container->tag('services', TaggedScopedService::class);

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        $first = $firstScope->tagged('services');
        $second = $secondScope->tagged('services');

        self::assertNotSame($first[0], $second[0]);

        $firstScope->close();
        $secondScope->close();

    }

    /**
     * ### Tagged services preserve mixed lifetimes
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedServicesPreserveMixedLifetimes ():void {

        $container = new Container;

        $container->singleton(TaggedSingletonService::class);
        $container->scoped(TaggedScopedService::class);
        $container->transient(TaggedTransientService::class);

        $container->tag(
            'services',
            TaggedSingletonService::class,
            TaggedScopedService::class,
            TaggedTransientService::class
        );

        $firstScope = $container->createScope();
        $secondScope = $container->createScope();

        $first = $firstScope->tagged('services');
        $second = $firstScope->tagged('services');
        $third = $secondScope->tagged('services');

        self::assertCount(3, $first);

        // Singleton instances are shared across scopes.
        self::assertSame($first[0], $second[0]);
        self::assertSame($first[0], $third[0]);

        // Scoped instances are shared only within their scope.
        self::assertSame($first[1], $second[1]);
        self::assertNotSame($first[1], $third[1]);

        // Transient instances are always recreated.
        self::assertNotSame($first[2], $second[2]);
        self::assertNotSame($first[2], $third[2]);

        $firstScope->close();
        $secondScope->close();

    }

    /**
     * ### Tagged singleton instances are shared between container and scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedSingletonInstancesAreSharedBetweenContainerAndScope ():void {

        $container = new Container;

        $container->singleton(TaggedSingletonService::class);

        $container->tag('services', TaggedSingletonService::class);

        $scope = $container->createScope();

        $fromContainer = $container->tagged('services');
        $fromScope = $scope->tagged('services');

        self::assertSame($fromContainer[0], $fromScope[0]);

        $scope->close();

    }

    /**
     * ### Tagged aliases resolve their target services
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedAliasesResolveTheirTargetServices ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $container->alias(
            TaggedLogger::class,
            TaggedFileLogger::class
        );

        $container->tag('logging', TaggedLogger::class);

        self::assertSame(
            [
                TaggedLogger::class
            ], $container->taggedIds('logging')
        );

        $services = $container->tagged('logging');

        self::assertCount(1, $services);
        self::assertInstanceOf(TaggedFileLogger::class, $services[0]);
        self::assertSame($services[0], $container->get(TaggedFileLogger::class));

    }

    /**
     * ### Tagged factory services are resolved
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedFactoryServicesAreResolved ():void {

        $container = new Container;

        $container->factory(
            TaggedFactoryService::class,
            static fn ($resolver):object => new TaggedFactoryService
        );

        $container->tag('services', TaggedFactoryService::class);

        $first = $container->tagged('services');
        $second = $container->tagged('services');

        self::assertInstanceOf(TaggedFactoryService::class, $first[0]);
        self::assertNotSame($first[0], $second[0]);

    }

    /**
     * ### Tagged factory services preserve singleton lifetime
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedFactoryServicesPreserveSingletonLifetime ():void {

        $container = new Container;

        $container->factory(
            TaggedFactoryService::class,
            static fn ($resolver):object => new TaggedFactoryService,
            \FireHub\Core\Meta\Enum\Container\Lifetime::SINGLETON
        );

        $container->tag('services', TaggedFactoryService::class);

        $first = $container->tagged('services');
        $second = $container->tagged('services');

        self::assertSame($first[0], $second[0]);

    }

    /**
     * ### Tags cannot be registered after freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagsCannotBeRegisteredAfterFreezing ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $container->freeze();

        $this->assertThrows(
            \FireHub\Container\Exception\ContainerFrozenException::class,
            function () use ($container):void {

                $container->tag('logging', TaggedFileLogger::class);

            }
        );

    }

    /**
     * ### Tagged services remain resolvable after freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedServicesRemainResolvableAfterFreezing ():void {

        $container = new Container;

        $container->singleton(TaggedFileLogger::class);

        $container->tag('logging', TaggedFileLogger::class);

        $container->freeze();

        self::assertTrue($container->hasTag('logging'));

        self::assertSame(
            [
                TaggedFileLogger::class
            ], $container->taggedIds('logging')
        );

        $services = $container->tagged('logging');

        self::assertInstanceOf(TaggedFileLogger::class, $services[0]);

    }

    /**
     * ### Tagged scoped services remain resolvable after freezing
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedScopedServicesRemainResolvableAfterFreezing ():void {

        $container = new Container;

        $container->scoped(TaggedScopedService::class);

        $container->tag('services', TaggedScopedService::class);

        $container->freeze();

        $scope = $container->createScope();

        $services = $scope->tagged('services');

        self::assertInstanceOf(TaggedScopedService::class, $services[0]);

        $scope->close();

    }

    /**
     * ### Tagged resolution fails after closing the scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedResolutionFailsAfterClosingTheScope ():void {

        $container = new Container;

        $container->scoped(TaggedScopedService::class);

        $container->tag('services', TaggedScopedService::class);

        $scope = $container->createScope();

        $scope->close();

        $this->assertThrows(
            \FireHub\Container\Exception\ScopeClosedException::class,
            function () use ($scope):void {

                $scope->tagged('services');

            }
        );

    }

    /**
     * ### Tagged resolution works inside scope run
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedResolutionWorksInsideScopeRun ():void {

        $container = new Container;

        $container->scoped(TaggedScopedService::class);

        $container->tag('services', TaggedScopedService::class);

        $scope = $container->createScope();

        $scope->run(
            function ($resolver) use ($scope):void {

                $services = $scope->tagged('services');

                self::assertCount(1, $services);
                self::assertInstanceOf(TaggedScopedService::class, $services[0]);

            }
        );

        self::assertTrue($scope->isClosed());

    }

    /**
     * ### Tags are isolated between containers
     * @since 1.0.0
     *
     * @return void
     */
    public function testTagsAreIsolatedBetweenContainers ():void {

        $firstContainer = new Container;
        $secondContainer = new Container;

        $firstContainer->singleton(TaggedFileLogger::class);
        $secondContainer->singleton(TaggedFileLogger::class);

        $firstContainer->tag('logging', TaggedFileLogger::class);

        self::assertTrue($firstContainer->hasTag('logging'));
        self::assertNotTrue($secondContainer->hasTag('logging'));

        self::assertSame([], $secondContainer->taggedIds('logging'));

    }

}
