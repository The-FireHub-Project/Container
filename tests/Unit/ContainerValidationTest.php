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
use FireHub\Container\Validation\ {
    Issue, IssueType, Result, Severity
};
use FireHub\Tests\Container\Stubs\ {
    Logger, LoggerInterface, TestFileLogger, TestConsoleLogger, ValidationConsoleLogger, ValidationConstructionProbe,
    ValidationFileLogger, ValidationLoggerInterface, ValidationUnrelatedService
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests static container definition validation
 * @since 1.0.0
 */
#[Small]
#[Group('validation')]
#[CoversClass(Container::class)]
final class ContainerValidationTest extends FireHubTestCase {

    /**
     * ### Validates an empty container
     * @since 1.0.0
     *
     * @return void
     */
    public function testEmptyContainerIsValid ():void {

        $container = new Container;

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertFalse($result->hasWarnings());
        self::assertSame([], $result->issues());
        self::assertSame([], $result->errors());
        self::assertSame([], $result->warnings());
        self::assertCount(0, $result);

    }

    /**
     * ### Validates a concrete class definition
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidClassDefinition ():void {

        $container = new Container;

        $container->singleton(Logger::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertCount(0, $result);

    }

    /**
     * ### Validates an interface implementation
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidInterfaceImplementation ():void {

        $container = new Container;

        $container->singleton(
            ValidationLoggerInterface::class,
            ValidationFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());

    }

    /**
     * ### Detects a missing implementation class
     * @since 1.0.0
     *
     * @return void
     */
    public function testMissingImplementationClass ():void {

        $container = new Container;

        $container->singleton(
            LoggerInterface::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingLogger'
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->errors());

        self::assertSame(
            IssueType::MISSING_CLASS,
            $result->errors()[0]->type
        );

        self::assertSame(
            Severity::ERROR,
            $result->errors()[0]->severity
        );

    }

    /**
     * ### Detects a non-instantiable implementation
     * @since 1.0.0
     *
     * @return void
     */
    public function testNonInstantiableClass ():void {

        $container = new Container;

        $container->singleton(LoggerInterface::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::MISSING_CLASS,
            $result->errors()[0]->type
        );

    }

    /**
     * ### Detects an incompatible class implementation
     * @since 1.0.0
     *
     * @return void
     */
    public function testIncompatibleImplementation ():void {

        $container = new Container;

        $container->singleton(
            LoggerInterface::class,
            Logger::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::TYPE_MISMATCH,
            $result->errors()[0]->type
        );

    }

    /**
     * ### Validates an existing service instance
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidInstanceDefinition ():void {

        $container = new Container;

        $container->instance(
            ValidationLoggerInterface::class,
            new ValidationFileLogger()
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Detects an incompatible service instance
     * @since 1.0.0
     *
     * @return void
     */
    public function testIncompatibleInstanceDefinition ():void {

        $container = new Container;

        $container->instance(
            LoggerInterface::class,
            new Logger
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::TYPE_MISMATCH,
            $result->errors()[0]->type
        );

    }

    /**
     * ### Validates a direct alias
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidDirectAlias ():void {

        $container = new Container;

        $container->singleton(ValidationFileLogger::class);

        $container->alias(
            ValidationLoggerInterface::class,
            ValidationFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates a chained alias
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidAliasChain ():void {

        $container = new Container;

        $container->singleton(ValidationFileLogger::class);

        $container->alias(
            ValidationLoggerInterface::class,
            ValidationFileLogger::class
        );

        $container->alias(
            'FireHub\\Tests\\Container\\Stubs\\LoggerAlias',
            ValidationLoggerInterface::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Detects an alias with a missing target
     * @since 1.0.0
     *
     * @return void
     */
    public function testMissingAliasTarget ():void {

        $container = new Container;

        $container->alias(
            LoggerInterface::class,
            TestFileLogger::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::INVALID_ALIAS,
            $result->errors()[0]->type
        );

    }

    /**
     * ### Detects a self-referencing alias
     * @since 1.0.0
     *
     * @return void
     */
    public function testSelfReferencingAlias ():void {

        $container = new Container;

        $container->alias(
            LoggerInterface::class,
            LoggerInterface::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::CIRCULAR_ALIAS,
            $result->errors()[0]->type
        );

    }

    /**
     * ### Detects a circular alias chain
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularAliasChain ():void {

        $container = new Container;

        $container->alias(
            LoggerInterface::class,
            TestFileLogger::class
        );

        $container->alias(
            TestFileLogger::class,
            LoggerInterface::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::CIRCULAR_ALIAS,
            $result->errors()[0]->type
        );

        self::assertSame(
            [
                LoggerInterface::class,
                TestFileLogger::class,
                LoggerInterface::class
            ],
            $result->errors()[0]->path
        );

    }

    /**
     * ### Detects an incompatible alias target
     * @since 1.0.0
     *
     * @return void
     */
    public function testIncompatibleAliasTarget ():void {

        $container = new Container;

        $container->singleton(Logger::class);

        $container->alias(
            LoggerInterface::class,
            Logger::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertSame(
            IssueType::TYPE_MISMATCH,
            $result->errors()[0]->type
        );

    }

    /**
     * ### Reports factory validation limitations
     * @since 1.0.0
     *
     * @return void
     */
    public function testFactoryProducesWarning ():void {

        $container = new Container;

        $container->factory(
            LoggerInterface::class,
            fn($resolver) => new TestFileLogger
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertTrue($result->hasWarnings());

        self::assertCount(1, $result->warnings());

        self::assertSame(
            IssueType::UNVERIFIABLE_FACTORY,
            $result->warnings()[0]->type
        );

        self::assertSame(
            Severity::WARNING,
            $result->warnings()[0]->severity
        );

    }

    /**
     * ### Collects multiple independent validation issues
     * @since 1.0.0
     *
     * @return void
     */
    public function testCollectsMultipleIssues ():void {

        $container = new Container;

        $container->singleton(
            ValidationLoggerInterface::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingLogger'
        );

        $container->alias(
            ValidationFileLogger::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingAliasTarget'
        );

        $container->factory(
            ValidationConsoleLogger::class,
            fn($resolver) => new TestConsoleLogger
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());
        self::assertTrue($result->hasWarnings());

        self::assertCount(2, $result->errors());
        self::assertCount(1, $result->warnings());

        self::assertSame(
            [
                IssueType::MISSING_CLASS,
                IssueType::INVALID_ALIAS
            ],
            array_map(
                static fn(Issue $issue):IssueType => $issue->type,
                $result->errors()
            )
        );

        self::assertSame(
            IssueType::UNVERIFIABLE_FACTORY,
            $result->warnings()[0]->type
        );


    }

    /**
     * ### Validation does not freeze the container
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidationDoesNotFreezeContainer ():void {

        $container = new Container;

        $container->singleton(Logger::class);

        $container->validate();

        self::assertFalse($container->isFrozen());

        $container->singleton(TestFileLogger::class);

        self::assertFalse($container->isFrozen());

    }

    /**
     * ### Validation does not instantiate registered services
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidationDoesNotInstantiateServices ():void {

        $container = new Container;

        $container->singleton(
            ValidationConstructionProbe::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertSame(
            0,
            ValidationConstructionProbe::$constructions
        );

    }

    /**
     * ### Validation does not execute factory callbacks
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidationDoesNotExecuteFactories ():void {

        $executions = 0;

        $container = new Container;

        $container->factory(
            LoggerInterface::class,
            function ($resolver) use (&$executions) {

                $executions++;

                return new TestFileLogger;

            }
        );

        $result = $container->validate();

        self::assertSame(0, $executions);
        self::assertTrue($result->isValid());
        self::assertTrue($result->hasWarnings());

    }

    /**
     * ### Validates frozen container definitions
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidatesFrozenContainer ():void {

        $container = new Container;

        $container->singleton(Logger::class);
        $container->freeze();

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertTrue($container->isFrozen());

    }

    /**
     * ### Validation result supports iteration
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidationResultIteration ():void {

        $issue = new Issue(
            IssueType::MISSING_CLASS,
            Severity::ERROR,
            Logger::class,
            'Missing implementation.'
        );

        $result = new Result([$issue]);

        self::assertCount(1, $result);
        self::assertSame([$issue], iterator_to_array($result));

    }

    /**
     * ### Warnings do not invalidate validation results
     * @since 1.0.0
     *
     * @return void
     */
    public function testWarningsDoNotInvalidateResult ():void {

        $issue = new Issue(
            IssueType::UNVERIFIABLE_FACTORY,
            Severity::WARNING,
            LoggerInterface::class,
           'Factory cannot be statically verified.'
        );

        $result = new Result([$issue]);

        self::assertTrue($result->isValid());
        self::assertTrue($result->hasWarnings());
        self::assertSame([], $result->errors());
        self::assertCount(1, $result->warnings());

    }

}