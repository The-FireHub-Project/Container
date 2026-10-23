<?php declare(strict_types = 1);

/**
 * This file is part of the FireHub Project ecosystem
 *
 * @author Danijel Galić <danijel.galic@outlook.com>
 * @copyright 2026-present The FireHub Project - All rights reserved
 * @license https://opensource.org/license/Apache-2-0 Apache License, Version 2.0
 *
 * @php-version >=7.4
 * @package Container
 */

namespace FireHub\Tests\Container\Unit;

use FireHub\Container\Container;
use FireHub\Container\Validation\ {
    Issue, IssueType, Result, Severity
};
use FireHub\Testing\FireHubTestCase;
use FireHub\Tests\Container\Stubs\ {
    CollectionValidationConsumer, CollectionValidationFirst, ContextualDnfConsumer, ContextualFileLogger,
    ContextualIntersectionConsumer, ContextualLogger, ContextualNamedConsumer, ContextualUnionConsumer,
    LifetimeValidationExplicitConsumer, LifetimeValidationFactoryConsumer, LifetimeValidationNestedSingleton,
    LifetimeValidationSafeConsumer, LifetimeValidationSafeSingleton, LifetimeValidationScoped,
    LifetimeValidationSingleton, LifetimeValidationTransient, TaggedFileLogger, TaggedValidationConsumer
};
use PHPUnit\Framework\Attributes\ {
    CoversClass, Group, Small
};

/**
 * ### Tests service lifetime validation
 * @since 1.0.0
 */
#[Small]
#[Group('validation')]
#[CoversClass(Container::class)]
final class LifetimeValidationTest extends FireHubTestCase {

    /**
     * ### Gets validation issues of a specified type
     * @since 1.0.0
     *
     * @return list<Issue>
     */
    private function issuesOfType (Result $result, IssueType $type):array {

        return array_values(array_filter(
            $result->issues(),
            static fn (Issue $issue):bool => $issue->type === $type
        ));

    }

    /**
     * ### Detects direct singleton-to-scoped dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testDirectSingletonScopedDependency ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->singleton(LifetimeValidationSingleton::class);

        $result = $container->validate();

        $issues = $this->issuesOfType(
            $result,
            IssueType::LIFETIME_MISMATCH
        );

        self::assertFalse($result->isValid());
        self::assertCount(1, $issues);
        self::assertSame(Severity::ERROR, $issues[0]->severity);
        self::assertSame(
            [
                LifetimeValidationSingleton::class,
                LifetimeValidationScoped::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Detects transitive singleton-to-scoped dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testTransitiveSingletonScopedDependency ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->transient(LifetimeValidationTransient::class);
        $container->singleton(LifetimeValidationNestedSingleton::class);

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                LifetimeValidationNestedSingleton::class,
                LifetimeValidationTransient::class,
                LifetimeValidationScoped::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Accepts singleton-to-singleton dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testSingletonDependencyIsValid ():void {

        $container = new Container;

        $container->singleton(LifetimeValidationSafeSingleton::class);
        $container->singleton(LifetimeValidationSafeConsumer::class);

        self::assertSame(
            [],
            $this->issuesOfType(
                $container->validate(),
                IssueType::LIFETIME_MISMATCH
            )
        );

    }

    /**
     * ### Accepts transient-to-scoped dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testTransientScopedDependencyIsValid ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);
        $container->transient(LifetimeValidationTransient::class);

        self::assertSame(
            [],
            $this->issuesOfType(
                $container->validate(),
                IssueType::LIFETIME_MISMATCH
            )
        );

    }

    /**
     * ### Ignores explicitly supplied constructor dependencies
     * @since 1.0.0
     *
     * @return void
     */
    public function testExplicitParameterBindingDoesNotCaptureScopedService ():void {

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);

        $container->singleton(LifetimeValidationExplicitConsumer::class);

        $container->bindParameters(
            LifetimeValidationExplicitConsumer::class,
            [
                'dependency' => new LifetimeValidationScoped
            ]
        );

        self::assertSame(
            [],
            $this->issuesOfType(
                $container->validate(),
                IssueType::LIFETIME_MISMATCH
            )
        );

    }

    /**
     * ### Does not execute factories during lifetime validation
     * @since 1.0.0
     *
     * @return void
     */
    public function testFactoryIsNotExecuted ():void {

        $executions = 0;

        $container = new Container;

        $container->scoped(LifetimeValidationScoped::class);

        $container->factory(
            LifetimeValidationFactoryConsumer::class,
            static function () use (&$executions):LifetimeValidationFactoryConsumer {

                $executions++;

                return new LifetimeValidationFactoryConsumer(
                    new LifetimeValidationScoped
                );

            }
        );

        $container->validate();

        self::assertSame(0, $executions);

    }

    /**
     * ### Detects scoped dependencies selected through contextual bindings
     * @since 1.0.0
     *
     * @return void
     */
    public function testContextualScopedDependency ():void {

        $container = new Container;

        $container->scoped(ContextualFileLogger::class);
        $container->singleton(ContextualNamedConsumer::class);

        $container->bindContextual(
            ContextualNamedConsumer::class,
            ContextualLogger::class,
            ContextualFileLogger::class
        );

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                ContextualNamedConsumer::class,
                ContextualFileLogger::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Detects scoped dependencies selected through union types
     * @since 1.0.0
     *
     * @return void
     */
    public function testUnionScopedDependency ():void {

        $container = new Container;

        $container->scoped(ContextualFileLogger::class);
        $container->singleton(ContextualUnionConsumer::class);

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                ContextualUnionConsumer::class,
                ContextualFileLogger::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Detects scoped dependencies selected through intersection types
     * @since 1.0.0
     *
     * @return void
     */
    public function testIntersectionScopedDependency ():void {

        $container = new Container;

        $container->scoped(ContextualFileLogger::class);
        $container->singleton(ContextualIntersectionConsumer::class);

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                ContextualIntersectionConsumer::class,
                ContextualFileLogger::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Detects scoped dependencies selected through DNF types
     * @since 1.0.0
     *
     * @return void
     */
    public function testDnfScopedDependency ():void {

        $container = new Container;

        $container->scoped(ContextualFileLogger::class);
        $container->singleton(ContextualDnfConsumer::class);

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                ContextualDnfConsumer::class,
                ContextualFileLogger::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Detects scoped dependencies injected through tags
     * @since 1.0.0
     *
     * @return void
     */
    public function testTaggedScopedDependency ():void {

        $container = new Container;

        $container->scoped(TaggedFileLogger::class);
        $container->singleton(TaggedValidationConsumer::class);

        $container->tag(
            'validation.services',
            TaggedFileLogger::class
        );

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                TaggedValidationConsumer::class,
                TaggedFileLogger::class
            ],
            $issues[0]->path
        );

    }

    /**
     * ### Detects scoped dependencies injected through collections
     * @since 1.0.0
     *
     * @return void
     */
    public function testCollectionScopedDependency ():void {

        $container = new Container;

        $container->scoped(CollectionValidationFirst::class);
        $container->singleton(CollectionValidationConsumer::class);

        $issues = $this->issuesOfType(
            $container->validate(),
            IssueType::LIFETIME_MISMATCH
        );

        self::assertCount(1, $issues);

        self::assertSame(
            [
                CollectionValidationConsumer::class,
                CollectionValidationFirst::class
            ],
            $issues[0]->path
        );

    }

}