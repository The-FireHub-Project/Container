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

use FireHub\Container\Attribute\ {
    InjectAll, Tagged
};
use FireHub\Container\Container;
use FireHub\Container\Exception\ {
    InvalidMethodArgumentException, ScopeClosedException, ScopeRequiredException, ServiceInstantiationException
};
use FireHub\Testing\FireHubTestCase;
use FireHub\Tests\Container\Stubs\ {
    CollectionConsoleLogger, CollectionFileLogger, CollectionLogger, ContextualFileLogger, ContextualLogger, Logger,
    ScopedDependency, TaggedConsoleLogger, TaggedFileLogger
};
use FireHub\Tests\Container\Support\ExceptionAssertions;
use LogicException;
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests callable dependency injection and invocation arguments
 *
 * Covers supported callback forms, explicit argument precedence, signature validation, contextual bindings,
 * collection attributes, scopes, and exception propagation.
 * @since 1.0.0
 */
#[Small]
#[Group('container')]
#[CoversClass(Container::class)]
final class MethodInjectionTest extends FireHubTestCase {

    /**
     * @since 1.0.0
     */
    use ExceptionAssertions;

    /**
     * ### Closure receives an implicitly constructed dependency
     * @since 1.0.0
     *
     * @return void
     */
    public function testClosureReceivesImplicitDependency ():void {

        $result = (new Container)->invoke(
            static fn (Logger $logger):object => $logger
        );

        self::assertInstanceOf(Logger::class, $result);

    }

    /**
     * ### Singleton dependency is reused across invocations
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingletonDependencyIsReused ():void {

        $container = new Container;
        $container->singleton(Logger::class);

        $first = $container->invoke(static fn (Logger $logger):Logger => $logger);
        $second = $container->invoke(static fn (Logger $logger):Logger => $logger);

        self::assertSame($first, $second);
        self::assertSame($first, $container->get(Logger::class));

    }

    /**
     * ### Explicit named value overrides dependency injection
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitNamedDependencyOverridesAutowiring ():void {

        $explicit = new Logger;

        $result = (new Container)->invoke(
            static fn (Logger $logger):Logger => $logger,
            ['logger' => $explicit]
        );

        self::assertSame($explicit, $result);

    }

    /**
     * ### Explicit positional value overrides dependency injection
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitPositionalDependencyOverridesAutowiring ():void {

        $explicit = new Logger;

        $result = (new Container)->invoke(
            static fn (Logger $logger):Logger => $logger,
            [$explicit]
        );

        self::assertSame($explicit, $result);

    }

    /**
     * ### Named scalar argument is combined with an injected service
     * @since 1.0.0
     *
     * @return void
     */
    public function testNamedScalarArgumentWithInjectedDependency ():void {

        $result = (new Container)->invoke(
            static fn (Logger $logger, string $message):array => [$logger, $message],
            ['message' => 'FireHub']
        );

        self::assertInstanceOf(Logger::class, $result[0]);
        self::assertSame('FireHub', $result[1]);

    }

    /**
     * ### Multiple named arguments may be supplied out of declaration order
     * @since 1.0.0
     *
     * @return void
     */
    public function testNamedArgumentsCanBeOutOfOrder ():void {

        $result = (new Container)->invoke(
            static fn (string $first, string $second):array => [$first, $second],
            ['second' => 'B', 'first' => 'A']
        );

        self::assertSame(['A', 'B'], $result);

    }

    /**
     * ### Positional and named arguments can target different parameters
     * @since 1.0.0
     *
     * @return void
     */
    public function testPositionalAndNamedArgumentsCanBeCombined ():void {

        $result = (new Container)->invoke(
            static fn (string $first, string $second, string $third):array => [$first, $second, $third],
            [0 => 'A', 'third' => 'C', 'second' => 'B']
        );

        self::assertSame(['A', 'B', 'C'], $result);

    }

    /**
     * ### Missing optional argument uses its declared default
     * @since 1.0.0
     *
     * @return void
     */
    public function testDefaultArgumentIsApplied ():void {

        self::assertSame(
            'default',
            (new Container)->invoke(static fn (string $value = 'default'):string => $value)
        );

    }

    /**
     * ### Explicit null is not mistaken for an omitted argument
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitNullOverridesDefault ():void {

        self::assertNull(
            (new Container)->invoke(
                static fn (?string $value = 'default'):?string => $value,
                ['value' => null]
            )
        );

    }

    /**
     * ### Required unbound scalar dependency fails
     * @since 1.0.0
     *
     * @return void
     */
    public function testMissingRequiredScalarThrows ():void {

        $this->assertThrows(
            ServiceInstantiationException::class,
            static function ():void {
                (new Container)->invoke(static fn (string $required):string => $required);
            }
        );

    }

    /**
     * ### Instance method receives dependencies and explicit arguments
     * @since 1.0.0
     *
     * @return void
     */
    public function testInstanceMethodInjection ():void {

        $fixture = new MethodInvocationFixture;

        $result = (new Container)->invoke(
            [$fixture, 'execute'],
            ['message' => 'instance']
        );

        self::assertSame('instance', $result);

    }

    /**
     * ### Static method can be invoked through array notation
     * @since 1.0.0
     *
     * @return void
     */
    public function testStaticMethodArrayCallable ():void {

        self::assertSame(
            Logger::class,
            (new Container)->invoke([MethodInvocationFixture::class, 'staticExecute'])
        );

    }

    /**
     * ### Static method can be invoked through string notation
     * @since 1.0.0
     *
     * @return void
     */
    public function testStaticMethodStringCallable ():void {

        self::assertSame(
            Logger::class,
            (new Container)->invoke(MethodInvocationFixture::class . '::staticExecute')
        );

    }

    /**
     * ### Invokable object receives dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvokableObjectInjection ():void {

        self::assertSame(
            Logger::class,
            (new Container)->invoke(new MethodInvocationFixture)
        );

    }

    /**
     * ### Named function can receive dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testNamedFunctionInjection ():void {

        self::assertSame(
            Logger::class,
            (new Container)->invoke(__NAMESPACE__ . '\\methodInjectionFunction')
        );

    }

    /**
     * ### Empty variadic parameter produces an empty list
     * @since 1.0.0
     *
     * @return void
     */
    public function testEmptyVariadicArgumentList ():void {

        self::assertSame(
            [],
            (new Container)->invoke(static fn (Logger $logger, string ...$messages):array => $messages)
        );

    }

    /**
     * ### Named variadic list is expanded into individual values
     * @since 1.0.0
     *
     * @return void
     */
    public function testNamedVariadicArguments ():void {

        self::assertSame(
            ['one', 'two', 'three'],
            (new Container)->invoke(
                static fn (Logger $logger, string ...$messages):array => $messages,
                ['messages' => ['one', 'two', 'three']]
            )
        );

    }

    /**
     * ### Remaining positional arguments are assigned to variadic parameter
     * @since 1.0.0
     *
     * @return void
     */
    public function testPositionalVariadicArguments ():void {

        self::assertSame(
            ['one', 'two'],
            (new Container)->invoke(
                static fn (string ...$messages):array => $messages,
                ['one', 'two']
            )
        );

    }

    /**
     * ### Unknown named argument is rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnknownNamedArgumentThrows ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(static fn ():bool => true, ['unknown' => 1]);
            }
        );

    }

    /**
     * ### Duplicate positional and named assignment is rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testDuplicateArgumentAssignmentThrows ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(
                    static fn (string $name):string => $name,
                    [0 => 'first', 'name' => 'second']
                );
            }
        );

    }

    /**
     * ### Named arguments cannot precede positional arguments
     * @since 1.0.0
     *
     * @return void
     */
    public function testPositionalAfterNamedThrows ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(
                    static fn (string $first, string $second):array => [$first, $second],
                    ['second' => 'B', 0 => 'A']
                );
            }
        );

    }

    /**
     * ### Nonconsecutive positional indexes are rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testNonconsecutivePositionalIndexesThrow ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(
                    static fn (string $first, string $second):array => [$first, $second],
                    [1 => 'B']
                );
            }
        );

    }

    /**
     * ### Excess positional arguments are rejected
     * @since 1.0.0
     *
     * @return void
     */
    public function testTooManyPositionalArgumentsThrow ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(static fn ():bool => true, ['extra']);
            }
        );

    }

    /**
     * ### Named variadic argument requires a list
     * @since 1.0.0
     *
     * @return void
     */
    public function testNamedVariadicScalarThrows ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(
                    static fn (string ...$messages):array => $messages,
                    ['messages' => 'not-a-list']
                );
            }
        );

    }

    /**
     * ### Associative array is not accepted as a named variadic list
     * @since 1.0.0
     *
     * @return void
     */
    public function testNamedVariadicAssociativeArrayThrows ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(
                    static fn (string ...$messages):array => $messages,
                    ['messages' => ['label' => 'value']]
                );
            }
        );

    }

    /**
     * ### Named and positional variadic values cannot be combined
     * @since 1.0.0
     *
     * @return void
     */
    public function testDuplicateVariadicSourcesThrow ():void {

        $this->assertThrows(
            InvalidMethodArgumentException::class,
            static function ():void {
                (new Container)->invoke(
                    static fn (string ...$messages):array => $messages,
                    [0 => 'first', 'messages' => ['second']]
                );
            }
        );

    }

    /**
     * ### Explicit constructor bindings do not apply to method injection
     * @since 1.0.0
     *
     * @return void
     */
    public function testConstructorParameterBindingsDoNotApplyToMethods ():void {

        $container = new Container;
        $container->bindParameters(MethodInvocationFixture::class, ['message' => 'constructor-only']);

        $fixture = new MethodInvocationFixture;

        self::assertSame('default', $container->invoke([$fixture, 'execute']));

    }

    /**
     * ### Contextual binding is applied to declaring a method class
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingOnInstanceMethod ():void {

        $container = new Container;
        $container->bindContextual(
            MethodInvocationFixture::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        self::assertInstanceOf(
            ContextualFileLogger::class,
            $container->invoke([new MethodInvocationFixture, 'contextual'])
        );

    }

    /**
     * ### Tagged injection returns services in tag registration order
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedMethodInjection ():void {

        $container = new Container;
        $container->singleton(TaggedFileLogger::class);
        $container->singleton(TaggedConsoleLogger::class);
        $container->tag('logging', TaggedConsoleLogger::class, TaggedFileLogger::class);

        $result = $container->invoke(
            static fn (#[Tagged('logging')] array $loggers):array => $loggers
        );

        self::assertCount(2, $result);
        self::assertInstanceOf(TaggedConsoleLogger::class, $result[0]);
        self::assertInstanceOf(TaggedFileLogger::class, $result[1]);

    }

    /**
     * ### Collection injection uses the registered matching services
     * @since 1.0.0
     *
     * @return void
     */
    public function testCollectionMethodInjection ():void {

        $container = new Container;
        $container->singleton(CollectionFileLogger::class);
        $container->singleton(CollectionConsoleLogger::class);

        $result = $container->invoke(
            static fn (#[InjectAll(CollectionLogger::class)] array $loggers):array => $loggers
        );

        self::assertCount(2, $result);
        self::assertInstanceOf(CollectionFileLogger::class, $result[0]);
        self::assertInstanceOf(CollectionConsoleLogger::class, $result[1]);

    }

    /**
     * ### Scoped dependency cannot be resolved directly from the container
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedDependencyRequiresScope ():void {

        $container = new Container;
        $container->scoped(ScopedDependency::class);

        $this->assertThrows(
            ScopeRequiredException::class,
            static function () use ($container):void {
                $container->invoke(static fn (ScopedDependency $service):object => $service);
            }
        );

    }

    /**
     * ### Scoped service instance is reused across invocations in the same scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedInvocationReusesScopedDependency ():void {

        $container = new Container;
        $container->scoped(ScopedDependency::class);
        $scope = $container->createScope();

        try {

            $first = $scope->invoke(static fn (ScopedDependency $service):object => $service);
            $second = $scope->invoke(static fn (ScopedDependency $service):object => $service);

            self::assertSame($first, $second);
            self::assertFalse($scope->isClosed());

        } finally {

            $scope->close();

        }

    }

    /**
     * ### Different scopes receive different scoped instances
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedInstancesAreIsolated ():void {

        $container = new Container;
        $container->scoped(ScopedDependency::class);
        $first_scope = $container->createScope();
        $second_scope = $container->createScope();

        try {

            $first = $first_scope->invoke(static fn (ScopedDependency $service):object => $service);
            $second = $second_scope->invoke(static fn (ScopedDependency $service):object => $service);

            self::assertNotSame($first, $second);

        } finally {

            $first_scope->close();
            $second_scope->close();

        }

    }

    /**
     * ### Invocation cannot use a closed scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvokingClosedScopeThrows ():void {

        $scope = (new Container)->createScope();
        $scope->close();

        $this->assertThrows(
            ScopeClosedException::class,
            static function () use ($scope):void {
                $scope->invoke(static fn ():bool => true);
            }
        );

    }

    /**
     * ### Callback failure propagates without being wrapped
     * @since 1.0.0
     *
     * @return void
     */
    public function testCallbackExceptionIsNotWrapped ():void {

        $expected = new LogicException('callback failure');

        try {

            (new Container)->invoke(static function () use ($expected):never {
                throw $expected;
            });

            self::fail('Expected callback exception was not thrown.');

        } catch (LogicException $exception) {

            self::assertSame($expected, $exception);

        }

    }

    /**
     * ### Scoped callback failure does not close the scope
     * @since 1.0.0
     *
     * @return void
     */
    public function testScopedCallbackExceptionLeavesScopeUsable ():void {

        $scope = (new Container)->createScope();
        $expected = new LogicException('callback failure');

        try {

            try {

                $scope->invoke(static function () use ($expected):never {
                    throw $expected;
                });

                self::fail('Expected callback exception was not thrown.');

            } catch (LogicException $exception) {

                self::assertSame($expected, $exception);

            }

            self::assertFalse($scope->isClosed());
            self::assertTrue($scope->invoke(static fn ():bool => true));

        } finally {

            $scope->close();

        }

    }

    /**
     * ### A frozen container still supports invocation
     * @since 1.0.0
     *
     * @return void
     */
    public function testFrozenContainerCanInvoke ():void {

        $container = new Container;
        $container->singleton(Logger::class);
        $container->freeze();

        self::assertInstanceOf(
            Logger::class,
            $container->invoke(static fn (Logger $logger):Logger => $logger)
        );

    }

}

/**
 * ### Provides callable fixtures for method injection tests
 * @since 1.0.0
 */
final class MethodInvocationFixture {

    /**
     * ### Executes an instance method with an injected dependency
     * @since 1.0.0
     *
     * @param \FireHub\Tests\Container\Stubs\Logger $logger <p>
     * Logger dependency.
     * </p>
     * @param string $message [optional] <p>
     * Message to return.
     * </p>
     *
     * @return string Explicit message.
     */
    public function execute (Logger $logger, string $message = 'default'):string {

        return $message;

    }

    /**
     * ### Resolves a dependency in a static method
     * @since 1.0.0
     *
     * @param \FireHub\Tests\Container\Stubs\Logger $logger <p>
     * Logger dependency.
     * </p>
     *
     * @return class-string<\FireHub\Tests\Container\Stubs\Logger> Logger class name.
     */
    public static function staticExecute (Logger $logger):string {

        return $logger::class;

    }

    /**
     * ### Resolves a contextual dependency
     * @since 1.0.0
     *
     * @param \FireHub\Tests\Container\Stubs\ContextualLogger $logger <p>
     * Contextually selected logger.
     * </p>
     *
     * @return \FireHub\Tests\Container\Stubs\ContextualLogger Selected logger.
     */
    public function contextual (ContextualLogger $logger):ContextualLogger {

        return $logger;

    }

    /**
     * ### Invokes an object with an injected logger
     * @since 1.0.0
     *
     * @param \FireHub\Tests\Container\Stubs\Logger $logger <p>
     * Logger dependency.
     * </p>
     *
     * @return class-string<\FireHub\Tests\Container\Stubs\Logger> Logger class name.
     */
    public function __invoke (Logger $logger):string {

        return $logger::class;

    }

}

/**
 * ### Named function fixture for callable injection
 * @since 1.0.0
 *
 * @param \FireHub\Tests\Container\Stubs\Logger $logger <p>
 * Logger dependency.
 * </p>
 *
 * @return class-string<\FireHub\Tests\Container\Stubs\Logger> Logger class name.
 */
function methodInjectionFunction (Logger $logger):string {

    return $logger::class;

}
