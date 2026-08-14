<?php

declare(strict_types=1);

/**
 * @template T of object
 */
final class SatisfiedBox
{
    /**
     * @var list<T>
     */
    public array $items = [];

    /**
     * `static` and `$this` carry the template arguments of the enclosing instance.
     */
    public function itself(): static
    {
        return $this;
    }
}

/**
 * A generic whose every template parameter has a default is usable bare.
 *
 * @template T of object = stdClass
 * @template U = string
 */
final class FullyDefaultedBox
{
    /**
     * @var list<T>
     */
    public array $items = [];

    /**
     * @var null|U
     */
    public mixed $label = null;
}

/**
 * @param SatisfiedBox<stdClass> $box
 */
function satisfied_userland_parameter(SatisfiedBox $box): int
{
    return count($box->items);
}

/**
 * @param SatisfiedBox<stdClass> $box
 *
 * @return SatisfiedBox<stdClass>
 */
function satisfied_userland_return(SatisfiedBox $box): SatisfiedBox
{
    return $box;
}

/**
 * @param ArrayObject<array-key, string> $collection
 */
function satisfied_builtin_array_object(ArrayObject $collection): int
{
    return $collection->count();
}

/**
 * @param SplObjectStorage<stdClass, null> $storage
 */
function satisfied_builtin_spl_object_storage(SplObjectStorage $storage): int
{
    return $storage->count();
}

/**
 * @param IteratorAggregate<int, string> $items
 */
function satisfied_builtin_iterator_aggregate(IteratorAggregate $items): void
{
}

/**
 * Non-generic class-likes are never reported.
 */
function satisfied_non_generic(stdClass $value): bool
{
    return isset($value->id);
}

function satisfied_fully_defaulted(FullyDefaultedBox $box): int
{
    return count($box->items);
}
