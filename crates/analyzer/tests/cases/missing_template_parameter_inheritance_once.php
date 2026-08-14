<?php

declare(strict_types=1);

/**
 * @template T of object
 */
class InheritedBox
{
    /**
     * @var list<T>
     */
    public array $items = [];
}

/**
 * @template T of object
 */
interface InheritedContract
{
    /**
     * @param T $item
     */
    public function add(object $item): void;
}

/**
 * The inheritance clause is checked by the class-like analyzer, not by the type-hint
 * check. Each `@mago-expect` below is consumed after a single match, so a duplicate
 * report would leave a second, unsuppressed issue and fail this test.
 *
 * @mago-expect analysis:missing-template-parameter
 */
final class InheritedBoxChild extends InheritedBox
{
}

/**
 * @mago-expect analysis:missing-template-parameter
 */
final class InheritedContractImplementor implements InheritedContract
{
    #[Override]
    public function add(object $item): void
    {
    }
}

/**
 * @extends InheritedBox<stdClass>
 */
final class WellFormedChild extends InheritedBox
{
}
