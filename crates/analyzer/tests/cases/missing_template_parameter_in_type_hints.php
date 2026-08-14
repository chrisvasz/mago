<?php

declare(strict_types=1);

/**
 * @template T of object
 */
final class TypeHintBox
{
    /**
     * @var list<T>
     */
    public array $items = [];
}

/**
 * @mago-expect analysis:missing-template-parameter
 */
function type_hint_userland_parameter(TypeHintBox $box): int
{
    return count($box->items);
}

/**
 * @mago-expect analysis:missing-template-parameter
 */
function type_hint_userland_return(): TypeHintBox
{
    return new TypeHintBox();
}

// The issue is anchored to the docblock type, which sits outside the scope a docblock
// pragma covers, so the expectation goes in its own preceding comment.
// @mago-expect analysis:missing-template-parameter
/**
 * A docblock that still omits the arguments is just as unspecified as the native hint.
 *
 * @param TypeHintBox $box
 */
function type_hint_userland_docblock_parameter(TypeHintBox $box): int
{
    return count($box->items);
}

/**
 * @mago-expect analysis:missing-template-parameter
 */
function type_hint_builtin_array_object(ArrayObject $collection): int
{
    return $collection->count();
}

/**
 * @mago-expect analysis:missing-template-parameter
 */
function type_hint_builtin_spl_object_storage(SplObjectStorage $storage): int
{
    return $storage->count();
}

/**
 * `IteratorAggregate` is auto-filled with `mixed` defaults when written bare, so the
 * arguments are present in the type but were never written by the user.
 *
 * @mago-expect analysis:missing-template-parameter
 */
function type_hint_builtin_iterator_aggregate(IteratorAggregate $items): void
{
}

/**
 * A nullable union still names the generic class.
 *
 * @mago-expect analysis:missing-template-parameter
 */
function type_hint_nullable_parameter(?TypeHintBox $box): bool
{
    return null !== $box;
}

// @mago-expect analysis:missing-template-parameter
/**
 * Nested type arguments are checked too.
 *
 * @param list<TypeHintBox> $boxes
 */
function type_hint_nested_parameter(array $boxes): int
{
    return count($boxes);
}

final class TypeHintHolder
{
    /**
     * @mago-expect analysis:missing-template-parameter
     */
    public ?TypeHintBox $box = null;

    /**
     * @mago-expect analysis:missing-template-parameter
     */
    public function withBox(TypeHintBox $box): void
    {
        $this->box = $box;
    }
}
