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
    CircularA, CircularB, CollectionInjectionConflictingAttributesConsumer, CollectionValidationCircular,
    CollectionValidationConsumer, CollectionValidationContract, CollectionValidationFirst,
    CollectionValidationMissingDependency, CollectionValidationNested, CollectionValidationSecond, ContextualCache,
    ContextualCacheService, ContextualCircularA, ContextualCircularB, ContextualDnfConsumer, ContextualFileLogger,
    ContextualIntersectionConsumer, ContextualLogger, ContextualNamedConsumer, ContextualUnionConsumer, DnfCacheService,
    DnfDefaultConsumer, DnfFileLogger, DnfFirstBranchConsumer, DnfNestedConsumer, DnfNoCandidateConsumer,
    DnfRequiredNullableConsumer, DnfSecondBranchConsumer, DnfSerializableCache, DnfTwoIntersectionsConsumer,
    DnfUniversalService, IntersectionConsumer, Logger, LoggerInterface, ParameterBindingValidationComposite,
    ParameterBindingValidationService, TaggedCircularConsumer, TaggedValidationConsumer, TestFileLogger, TestLogger,
    UnionA, UnionAmbiguous, UnionB, UnionNullable, UnionOnlyA, ValidationConstructionProbe
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

    /**
     * ### Validates a union dependency with one eligible candidate
     *
     * A union dependency with a uniquely selectable implementation must pass static dependency graph validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionDependencyWithSingleCandidate ():void {

        $container = new Container;

        $container->singleton(UnionOnlyA::class);
        $container->singleton(UnionA::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::AMBIGUOUS_DEPENDENCY
            )
        );

    }

    /**
     * ### Detects ambiguous union dependencies
     *
     * Multiple eligible union alternatives must produce an ambiguous dependency validation issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testAmbiguousUnionDependency ():void {

        $container = new Container;

        $container->singleton(UnionAmbiguous::class);

        $container->singleton(UnionA::class);
        $container->singleton(UnionB::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::AMBIGUOUS_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates nullable union dependencies with default values
     *
     * A nullable union dependency with an explicit default value must remain valid when no dependency candidate is
     * available.
     * @since 1.0.0
     *
     * @return void
     */
    public function testNullableUnionDependencyWithDefault ():void {

        $container = new Container;

        $container->singleton(UnionNullable::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::MISSING_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates an intersection dependency with one eligible candidate
     *
     * An intersection dependency must be considered valid when exactly one registered implementation satisfies all
     * declared contracts.
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionDependencyWithSingleCandidate ():void {

        $container = new Container;

        $container->singleton(IntersectionConsumer::class);
        $container->singleton(TestFileLogger::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::MISSING_DEPENDENCY
            )
        );

    }

    /**
     * ### Detects missing intersection dependencies
     *
     * An intersection dependency without an implementation satisfying every required contract must produce a missing
     * dependency issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionDependencyWithoutCandidate ():void {

        $container = new Container;

        $container->singleton(IntersectionConsumer::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::MISSING_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates the first DNF intersection branch
     *
     * A DNF dependency must pass validation when one registered implementation satisfies the first intersection branch.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfFirstBranchValidation ():void {

        $container = new Container;

        $container->singleton(DnfFirstBranchConsumer::class);
        $container->singleton(DnfFileLogger::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates the second DNF-named branch
     *
     * A DNF dependency must pass validation when one registered implementation satisfies its named alternative.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfSecondBranchValidation ():void {

        $container = new Container;

        $container->singleton(DnfSecondBranchConsumer::class);
        $container->singleton(DnfCacheService::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates a DNF dependency containing two intersection branches
     *
     * A registered implementation satisfying either complete intersection branch must be accepted by dependency
     * graph validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfTwoIntersectionBranches ():void {

        $container = new Container;

        $container->singleton(DnfTwoIntersectionsConsumer::class);
        $container->singleton(DnfSerializableCache::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates an implementation satisfying multiple DNF branches
     *
     * A single registered implementation satisfying more than one DNF branch must not be considered ambiguous.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfSingleImplementationMatchingMultipleBranches ():void {

        $container = new Container;

        $container->singleton(DnfTwoIntersectionsConsumer::class);
        $container->singleton(DnfUniversalService::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Detects ambiguous DNF dependencies
     *
     * Multiple registered implementations satisfying different DNF alternatives must produce an ambiguous dependency
     * issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testAmbiguousDnfDependency ():void {

        $container = new Container;

        $container->singleton(DnfFirstBranchConsumer::class);

        $container->singleton(DnfFileLogger::class);
        $container->singleton(DnfCacheService::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::AMBIGUOUS_DEPENDENCY
            )
        );

    }

    /**
     * ### Detects missing DNF dependency candidates
     *
     * A required DNF dependency must produce a missing dependency issue when no registered implementation satisfies
     * any complete branch.
     * @since 1.0.0
     *
     * @return void
     */
    public function testMissingDnfDependency ():void {

        $container = new Container;

        $container->singleton(DnfNoCandidateConsumer::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::MISSING_DEPENDENCY
            )
        );

    }

    /**
     * ### Accepts a DNF dependency with a null default
     *
     * A DNF constructor parameter with an explicit null default must remain valid when no eligible implementation is
     * registered.
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfDependencyWithNullDefault ():void {

        $container = new Container;

        $container->singleton(DnfDefaultConsumer::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Rejects a required nullable DNF dependency without candidates
     *
     * Nullable constructor parameters without explicit default values remain required and must produce a missing
     * dependency issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testRequiredNullableDnfDependency ():void {

        $container = new Container;

        $container->singleton(DnfRequiredNullableConsumer::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::MISSING_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates nested DNF constructor dependencies
     *
     * Dependency graph validation must recursively inspect constructor dependencies selected through DNF types.
     * @since 1.0.0
     *
     * @return void
     */
    public function testNestedDnfDependencyGraph ():void {

        $container = new Container;

        $container->singleton(DnfNestedConsumer::class);
        $container->singleton(DnfFileLogger::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::MISSING_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates an empty tagged collection
     *
     * An unknown tag must produce an empty collection without introducing
     * missing dependency validation issues.
     * @since 1.0.0
     *
     * @return void
     */
    public function testEmptyTaggedCollectionValidation ():void {

        $container = new Container;

        $container->singleton(TaggedValidationConsumer::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType($result, IssueType::MISSING_DEPENDENCY)
        );

    }

    /**
     * ### Validates multiple tagged services
     *
     * Every tagged service must be included in dependency graph validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testMultipleTaggedServicesValidation ():void {

        $container = new Container;

        $container->singleton(TaggedValidationConsumer::class);
        $container->singleton(Logger::class);
        $container->singleton(ValidationConstructionProbe::class);

        $container->tag(
            'validation.services',
            Logger::class,
            ValidationConstructionProbe::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Detects circular dependencies through tagged injection
     *
     * Tagged collection traversal must preserve the active dependency path
     * so that circular dependencies are detected.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularTaggedDependencyValidation ():void {

        $container = new Container;

        $container->singleton(TaggedCircularConsumer::class);

        $container->tag(
            'validation.circular',
            TaggedCircularConsumer::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType($result, IssueType::CIRCULAR_DEPENDENCY)
        );

    }

    /**
     * ### Validates tagged aliases
     *
     * Tagged aliases must be resolved to their canonical service definitions during dependency graph validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedAliasValidation ():void {

        $container = new Container;

        $container->singleton(TaggedValidationConsumer::class);
        $container->singleton(TestFileLogger::class);

        $container->alias(
            TestLogger::class,
            TestFileLogger::class
        );

        $container->tag(
            'validation.services',
            TestLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Does not execute tagged factories during validation
     *
     * Factory callbacks must never be executed while inspecting tagged constructor dependencies.
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedFactoryIsNotExecutedDuringValidation ():void {

        $executed = false;

        $container = new Container;

        $container->singleton(TaggedValidationConsumer::class);

        $container->factory(
            LoggerInterface::class,
            function ($resolver) use (&$executed):object {

                $executed = true;

                return new Logger;

            }
        );

        $container->tag(
            'validation.services',
            LoggerInterface::class
        );

        $container->validate();

        self::assertFalse($executed);

    }

    /**
     * ### Validates an empty collection dependency
     *
     * Collection injection must remain valid when no matching service implementations are registered.
     * @since 1.0.0
     *
     * @return void
     */
    public function testEmptyCollectionDependencyValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates a collection with one implementation
     *
     * A collection containing one compatible registered implementation must pass static validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingleCollectionImplementationValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);
        $container->singleton(CollectionValidationFirst::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates a collection with multiple implementations
     *
     * All compatible registered implementations must be accepted without producing ambiguity errors.
     * @since 1.0.0
     *
     * @return void
     */
    public function testMultipleCollectionImplementationsValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);
        $container->singleton(CollectionValidationFirst::class);
        $container->singleton(CollectionValidationSecond::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType($result, IssueType::AMBIGUOUS_DEPENDENCY)
        );

    }

    /**
     * ### Ignores unrelated collection implementations
     *
     * Services that do not satisfy the requested collection element type must not affect collection validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnrelatedCollectionImplementationValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);
        $container->singleton(Logger::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates collection aliases without duplicate traversal
     *
     * Multiple identifiers resolving to the same canonical service must not introduce duplicate dependency issues.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCollectionAliasDeduplicationValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);
        $container->singleton(CollectionValidationFirst::class);

        $container->alias(
            CollectionValidationContract::class,
            CollectionValidationFirst::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Detects circular dependencies through collection injection
     *
     * A service included in its own injected collection must produce a circular dependency validation issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCircularCollectionDependencyValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationCircular::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType($result, IssueType::CIRCULAR_DEPENDENCY)
        );

    }

    /**
     * ### Detects missing dependencies inside collection members
     *
     * Collection validation must recursively inspect selected implementations and report unresolved required
     * constructor dependencies.
     * @since 1.0.0
     *
     * @return void
     */
    public function testMissingCollectionMemberDependencyValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);
        $container->singleton(CollectionValidationMissingDependency::class);

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertNotEmpty(
            $this->issuesOfType($result, IssueType::MISSING_DEPENDENCY)
        );

    }

    /**
     * ### Does not execute collection factories during validation
     *
     * Static collection validation must never invoke registered factory callbacks.
     * @since 1.0.0
     *
     * @return void
     */
    public function testCollectionFactoryIsNotExecutedDuringValidation ():void {

        $executed = false;

        $container = new Container;

        $container->singleton(CollectionValidationConsumer::class);

        $container->factory(
            CollectionValidationContract::class,
            function ($resolver) use (&$executed):object {

                $executed = true;

                return new CollectionValidationFirst;

            }
        );

        $container->validate();

        self::assertFalse($executed);

    }

    /**
     * ### Validates nested collection dependencies
     *
     * Collection injection must be analyzed recursively when encountered inside nested constructor dependency graphs.
     * @since 1.0.0
     *
     * @return void
     */
    public function testNestedCollectionDependencyValidation ():void {

        $container = new Container;

        $container->singleton(CollectionValidationNested::class);
        $container->singleton(CollectionValidationFirst::class);

        $result = $container->validate();

        self::assertTrue($result->isValid());

    }

    /**
     * ### Validates compatible scalar parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidScalarParameterBindings ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            [
                'port' => 3307,
                'host' => 'localhost',
                'timeout' => 2.5
            ]
        );

        self::assertTrue($container->validate()->isValid());

    }

    /**
     * ### Detects incompatible scalar parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidScalarParameterBinding ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['port' => 'invalid']
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType($result, IssueType::INVALID_PARAMETER_BINDING)
        );

    }

    /**
     * ### Accepts integer values for float parameters
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntegerToFloatParameterBinding ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['timeout' => 5]
        );

        self::assertTrue($container->validate()->isValid());

    }

    /**
     * ### Accepts null for nullable parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testNullableParameterBinding ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['host' => null]
        );

        self::assertTrue($container->validate()->isValid());

    }

    /**
     * ### Rejects null for non-nullable parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidNullableParameterBinding ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['port' => null]
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType($result, IssueType::INVALID_PARAMETER_BINDING)
        );

    }

    /**
     * ### Validates compatible object parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidObjectParameterBinding ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['dependency' => new ParameterBindingValidationService]
        );

        self::assertTrue($container->validate()->isValid());

    }

    /**
     * ### Detects incompatible object parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidObjectParameterBinding ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['dependency' => new \stdClass]
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType($result, IssueType::INVALID_PARAMETER_BINDING)
        );

    }

    /**
     * ### Validates compatible union parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidUnionParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationComposite::class,
            ['union' => 'value']
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Detects incompatible union parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidUnionParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationComposite::class,
            ['union' => new \stdClass]
        );

        $result = $container->validate();

        self::assertCount(
            1,
            $this->issuesOfType($result, IssueType::INVALID_PARAMETER_BINDING)
        );

    }

    /**
     * ### Validates compatible intersection parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidIntersectionParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationComposite::class,
            ['intersection' => new \ArrayObject]
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Detects incompatible intersection parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidIntersectionParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationComposite::class,
            ['intersection' => new \stdClass]
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Validates compatible DNF parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidDnfParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationComposite::class,
            ['dnf' => new \ArrayObject]
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Detects incompatible DNF parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidDnfParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationComposite::class,
            ['dnf' => new \stdClass]
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Detects bindings for nonexistent constructor parameters
     * @since 1.0.0
     *
     * @return void
     */
    public function testNonexistentParameterBinding ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['unknown' => 'value']
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Validates explicit bindings before constructor defaults
     * @since 1.0.0
     *
     * @return void
     */
    public function testInvalidBindingOverridesDefault ():void {

        $container = new Container;

        $container->singleton(ParameterBindingValidationService::class);

        $container->bindParameters(
            ParameterBindingValidationService::class,
            ['port' => 'invalid']
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Detects parameter bindings for unknown implementation classes
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnknownParameterBindingClass ():void {

        $container = new Container;

        $container->bindParameters(
            'FireHub\\Tests\\Container\\Stubs\\NonexistentBindingClass',
            ['value' => 1]
        );

        self::assertCount(
            1,
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Collects multiple invalid parameter bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testMultipleInvalidParameterBindings ():void {

        $container = new Container;

        $container->bindParameters(
            ParameterBindingValidationService::class,
            [
                'port' => 'invalid',
                'host' => new \stdClass,
                'timeout' => false
            ]
        );

        self::assertCount(
            3,
            $this->issuesOfType(
                $container->validate(),
                IssueType::INVALID_PARAMETER_BINDING
            )
        );

    }

    /**
     * ### Validates a named contextual dependency binding
     *
     * A compatible contextual implementation must satisfy the consumer's declared dependency contract.
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidNamedContextualBinding ():void {

        $container = new Container;

        $container->singleton(ContextualNamedConsumer::class);

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::UNUSED_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Validates contextual bindings without consumer registration
     *
     * Contextual binding configuration must be validated independently of explicit consumer service registration.
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingWithoutRegisteredConsumer ():void {

        $container = new Container;

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::UNUSED_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Validates contextual bindings for union dependencies
     *
     * A contextual dependency contract matching one union member must be recognized as used.
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidUnionContextualBinding ():void {

        $container = new Container;

        $container->singleton(ContextualUnionConsumer::class);

        $container->bindContextual(
            ContextualUnionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::UNUSED_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Validates contextual bindings for intersection dependencies
     *
     * A contextual implementation satisfying every intersection member must pass static validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidIntersectionContextualBinding ():void {

        $container = new Container;

        $container->singleton(ContextualIntersectionConsumer::class);

        $container->bindContextual(
            ContextualIntersectionConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::UNUSED_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Validates contextual bindings for DNF dependencies
     *
     * Contextual dependency contracts referenced within nested DNF branches must be recognized during validation.
     * @since 1.0.0
     *
     * @return void
     */
    public function testValidDnfContextualBinding ():void {

        $container = new Container;

        $container->singleton(ContextualDnfConsumer::class);

        $container->bindContextual(
            ContextualDnfConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::UNUSED_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Detects contextual bindings referencing unknown consumers
     *
     * A contextual binding whose consumer class does not exist must produce an invalid contextual binding issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnknownContextualConsumer ():void {

        $container = new Container;

        $container->bindContextual(
            'FireHub\\Tests\\Container\\Stubs\\MissingContextualConsumer',
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Detects contextual bindings referencing unknown dependency contracts
     *
     * Contextual dependency contracts must reference existing classes or interfaces.
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnknownContextualDependency ():void {

        $container = new Container;

        $container->bindContextual(
            ContextualNamedConsumer::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingContextualContract',
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Detects contextual bindings referencing unknown implementations
     *
     * A contextual implementation must reference a registered service or an implicitly constructible class.
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnknownContextualImplementation ():void {

        $container = new Container;

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            'FireHub\\Tests\\Container\\Stubs\\MissingContextualImplementation'
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Detects incompatible contextual implementations
     *
     * A contextual implementation that does not satisfy its registered dependency contract must be rejected.
     * @since 1.0.0
     *
     * @return void
     */
    public function testIncompatibleContextualImplementation ():void {

        $container = new Container;

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            \stdClass::class
        );

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Reports contextual bindings unused by constructor injection
     *
     * A structurally valid contextual binding must produce a warning when its dependency contract is not referenced
     * by any eligible constructor parameter.
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnusedContextualBinding ():void {

        $container = new Container;

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualCache::class,
            ContextualCacheService::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        $issues = $this->issuesOfType(
            $result,
            IssueType::UNUSED_CONTEXTUAL_BINDING
        );

        self::assertCount(1, $issues);

        self::assertSame(
            Severity::WARNING,
            $issues[0]->severity
        );

    }

    /**
     * ### Reports contextual bindings shadowed by explicit parameter bindings
     *
     * Explicit constructor parameter bindings take precedence over contextual dependency resolution.
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingShadowedByParameterBinding ():void {

        $container = new Container;

        $container->singleton(ContextualNamedConsumer::class);

        $container->bindParameters(
            ContextualNamedConsumer::class,
            [
                'logger' => new ContextualFileLogger
            ]
        );

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::UNUSED_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Detects ambiguous contextual dependency candidates
     *
     * Two contextual bindings selecting distinct implementations for the same union parameter must produce an
     * ambiguous dependency issue.
     * @since 1.0.0
     *
     * @return void
     */
    public function testAmbiguousContextualBindings ():void {

        $container = new Container;

        $container->singleton(ContextualUnionConsumer::class);

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

        $result = $container->validate();

        self::assertFalse($result->isValid());

        self::assertCount(
            1,
            $this->issuesOfType(
                $result,
                IssueType::AMBIGUOUS_DEPENDENCY
            )
        );

    }

    /**
     * ### Validates contextual implementations referenced through service aliases
     *
     * A contextual implementation referenced through a valid alias must retain its underlying type compatibility.
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingWithAliasImplementation ():void {

        $container = new Container;

        $container->singleton(ContextualFileLogger::class);

        $container->alias(
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualLogger::class
        );

        $result = $container->validate();

        self::assertTrue($result->isValid());

        self::assertSame(
            [],
            $this->issuesOfType(
                $result,
                IssueType::INVALID_CONTEXTUAL_BINDING
            )
        );

    }

    /**
     * ### Does not execute contextual factory implementations during validation
     *
     * Static contextual binding validation must not execute registered factory callbacks.
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualBindingValidationDoesNotExecuteFactories ():void {

        $executions = 0;

        $container = new Container;

        $container->singleton(ContextualNamedConsumer::class);

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

        self::assertTrue($result->isValid());

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

}