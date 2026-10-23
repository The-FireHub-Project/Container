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
    CircularA, CircularB,ContextualCircularA, ContextualCircularB, CollectionInjectionConflictingAttributesConsumer,
    ContextualNamedConsumer, ContextualLogger, ContextualFileLogger, Logger, LoggerInterface, TestFileLogger,
    ValidationConstructionProbe, ValidationFileLogger
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests static constructor dependency graph validation
 * @since 1.0.0
 */
#[Small]
#[Group('validation')]
#[CoversClass(Container::class)]
final class DependencyGraphValidationTest extends FireHubTestCase {

    /**
     * ### Validates an acyclic dependency graph
     *
     * A constructor dependency graph without unresolved dependencies or circular relationships must produce no
     * validation errors.
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidDependencyGraph ():void {

        $container = new Container;

        $container->singleton(Logger::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());

    }

    /**
     * ### Detects a circular constructor dependency
     *
     * Two services depending on each other must produce a circular dependency validation error.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularDependency ():void {

        $container = new Container;

        $container->singleton(CircularA::class);
        $container->singleton(CircularB::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        $issues = $this->issuesOfType(
            $result,
            IssueType::CIRCULAR_DEPENDENCY
        );

        self::assertCount(1, $issues);

        self::assertSame(
            Severity::ERROR,
            $issues[0]->severity
        );

    }

    /**
     * ### Deduplicates circular dependency rotations
     *
     * The same directed dependency cycle must be reported only once even when multiple registered services provide
     * different traversal starting points.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularDependencyDeduplication ():void {

        $container = new Container;

        $container->singleton(CircularA::class);
        $container->singleton(CircularB::class);

        $result = $container->validate();

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::CIRCULAR_DEPENDENCY
            )
        );

    }

    /**
     * ### Preserves circular dependency paths
     *
     * Circular dependency issues must contain the dependency path, including the service identifier that closes the
     * cycle.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularDependencyPath ():void {

        $container = new Container;

        $container->singleton(CircularA::class);
        $container->singleton(CircularB::class);

        $result = $container->validate();

        $issues = $this->issuesOfType(
            $result,
            IssueType::CIRCULAR_DEPENDENCY
        );

        self::assertCount(1, $issues);

        $path = $issues[0]->path;

        self::assertGreaterThanOrEqual(3, count($path));

        self::assertSame(
            $path[0],
            $path[count($path) - 1]
        );

        self::assertContains(CircularA::class, $path);
        self::assertContains(CircularB::class, $path);

    }

    /**
     * ### Detects a circular alias chain
     *
     * Circular service aliases must be reported as alias validation errors rather than constructor dependency cycles.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularAliasDoesNotProduceDependencyCycle ():void {

        $container = new Container;

        $container->alias(
            ValidationFileLogger::class,
            TestFileLogger::class
        );

        $container->alias(
            TestFileLogger::class,
            ValidationFileLogger::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertNotEmpty(
            $this->issuesOfType(
                $result,
                IssueType::CIRCULAR_ALIAS
            )
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::CIRCULAR_DEPENDENCY
            )
        );

    }

    /**
     * ### Detects an invalid alias target
     *
     * An alias referencing an unregistered target must produce an invalid alias issue without reporting a
     * constructor dependency cycle.
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidAliasDoesNotProduceDependencyCycle ():void {

        $container = new Container;

        $container->alias(
            ValidationFileLogger::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingAliasTarget'
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertNotEmpty(
            $this->issuesOfType(
                $result,
                IssueType::INVALID_ALIAS
            )
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::CIRCULAR_DEPENDENCY
            )
        );

    }

    /**
     * ### Does not instantiate services during graph validation
     *
     * Constructor dependency graph validation must inspect metadata without creating registered service instances.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDependencyGraphValidationDoesNotInstantiateServices ():void {

        ValidationConstructionProbe::$constructions = 0;

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
     * ### Does not execute factories during graph validation
     *
     * Registered factory callbacks must never be executed during static dependency graph analysis.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDependencyGraphValidationDoesNotExecuteFactories ():void {

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

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::UNVERIFIABLE_FACTORY
            )
        );

    }

    /**
     * ### Preserves validation results across repeated executions
     *
     * Repeated validation of an unchanged container must produce equivalent diagnostic results without retaining the
     * cycle detection state.
     * @since 1.0.0
     *
     * @return void
     */
    public function testRepeatedValidationProducesEquivalentResults ():void {

        $container = new Container;

        $container->singleton(CircularA::class);
        $container->singleton(CircularB::class);

        $first = $container->validate();
        $second = $container->validate();

        self::assertEquals(
            $first->issues(),
            $second->issues()
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $second,
                IssueType::CIRCULAR_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates multiple independent services
     *
     * A circular dependency in one service graph must not prevent validation of other independently registered
     * services.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularDependencyDoesNotInterruptOtherValidation ():void {

        $container = new Container;

        $container->singleton(CircularA::class);
        $container->singleton(CircularB::class);

        $container->singleton(
            LoggerInterface::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingLogger'
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::CIRCULAR_DEPENDENCY
            )
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::MISSING_CLASS
            )
        );

    }

    /**
     * ### Filters validation issues by type
     *
     * Returns all validation issues matching the specified issue category.
     * @since 1.0.0
     *
     * @param \FireHub\Container\Validation\Result $result <p>
     * Container validation result.
     * </p>
     * @param \FireHub\Container\Validation\IssueType $type <p>
     * Requested validation issue type.
     * </p>
     *
     * @return list<\FireHub\Container\Validation\Issue> Matching validation issues.
     */
    private function issuesOfType (
        Result $result,
        IssueType $type
    ):array {

        return array_values(
            array_filter(
                $result->issues(),
                static fn (Issue $issue):bool => $issue->type === $type
            )
        );

    }

    /**
     * ### Detects an invalid constructor dependency plan
     *
     * Invalid injection attribute configurations must be reported as validation issues instead of interrupting the
     * validation process.
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidDependencyPlan ():void {

        $container = new Container;

        $container->singleton(
            CollectionInjectionConflictingAttributesConsumer::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::INVALID_DEPENDENCY_PLAN
            )
        );

    }

    /**
     * ### Continues validation after an invalid dependency plan
     *
     * A constructor dependency plan compilation failure must not prevent independently registered services from
     * being validated.
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidDependencyPlanDoesNotInterruptValidation ():void {

        $container = new Container;

        $container->singleton(
            CollectionInjectionConflictingAttributesConsumer::class
        );

        $container->singleton(
            LoggerInterface::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingLogger'
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::INVALID_DEPENDENCY_PLAN
            )
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::MISSING_CLASS
            )
        );

    }

    /**
     * ### Accepts a contextual factory target
     *
     * Contextual bindings referencing registered factories must not be rejected solely because their implementation
     * types cannot be statically verified.
     *
     * Factory callbacks must not execute during validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualFactoryTarget ():void {

        $executions = 0;

        $container = new Container;

        $container->singleton(
            ContextualNamedConsumer::class
        );

        $container->factory(
            ContextualLogger::class,
            function ($resolver) use (&$executions) {

                $executions++;

                return new ContextualFileLogger;

            }
        );

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualLogger::class
        );

        $result = $container->validate();

        self::assertSame(0, $executions);

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::UNVERIFIABLE_FACTORY
            )
        );

    }

    /**
     * ### Detects a circular contextual dependency
     *
     * Circular dependencies introduced through contextual bindings must be detected and reported as constructor
     * dependency cycles.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularContextualDependency ():void {

        $container = new Container;

        $container->singleton(
            ContextualCircularA::class
        );

        $container->singleton(
            ContextualCircularB::class
        );

        $container->bindContextual(
            ContextualCircularA::class,
            ContextualCircularB::class,
            ContextualCircularB::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::CIRCULAR_DEPENDENCY
            )
        );

    }

}