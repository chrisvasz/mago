<?php

declare(strict_types=1);

final class VoidProducer
{
    public static function act(): void {}
}

function void_result_used_takes_string(string $_value): void {}

function void_result_used_takes_mixed(mixed $_value): void {}

/**
 * @mago-expect analysis:void-result-used
 */
function void_result_assigned(): void
{
    $_value = VoidProducer::act();
}

/**
 * @mago-expect analysis:void-result-used
 */
function void_result_assigned_then_used(): void
{
    $value = VoidProducer::act();

    void_result_used_takes_mixed($value);
}

/**
 * @mago-expect analysis:void-result-used
 */
function void_result_as_unconstrained_argument(): void
{
    void_result_used_takes_mixed(VoidProducer::act());
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:invalid-argument
 */
function void_result_as_typed_argument(): void
{
    void_result_used_takes_string(VoidProducer::act());
}

/**
 * @mago-expect analysis:void-result-used
 */
function void_result_returned_as_mixed(): mixed
{
    return VoidProducer::act();
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:invalid-return-statement
 */
function void_result_returned_as_string(): string
{
    return VoidProducer::act();
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:invalid-operand
 */
function void_result_concatenated(): string
{
    return 'x' . VoidProducer::act();
}

/**
 * @mago-expect analysis:void-result-used
 *
 * @return array<array-key, mixed>
 */
function void_result_as_array_element(): array
{
    return [VoidProducer::act()];
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:invalid-array-element-key
 *
 * @return array<array-key, mixed>
 */
function void_result_as_array_key(): array
{
    return [VoidProducer::act() => 1];
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:impossible-condition
 */
function void_result_as_condition(): void
{
    if (VoidProducer::act()) {
    }
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:impossible-condition
 */
function void_result_as_elvis_subject(): mixed
{
    return VoidProducer::act() ?: 1;
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:redundant-comparison
 */
function void_result_compared_to_null(): bool
{
    return VoidProducer::act() === null;
}

/**
 * @mago-expect analysis:void-result-used
 */
function void_result_as_coalesce_subject(): mixed
{
    return VoidProducer::act() ?? 1;
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:invalid-iterator
 */
function void_result_iterated(): void
{
    foreach (VoidProducer::act() as $_item) {
    }
}

/**
 * @mago-expect analysis:void-result-used
 */
function void_result_assigned_to_property(): void
{
    $object = new stdClass();
    $object->property = VoidProducer::act();
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:possibly-invalid-argument
 */
function void_result_echoed(): void
{
    echo VoidProducer::act();
}

function void_result_discarded(bool $flag, ?int $maybe, int $limit): void
{
    VoidProducer::act();
    (VoidProducer::act());
    @VoidProducer::act();

    for (VoidProducer::act(); $limit > 0; VoidProducer::act()) {
        break;
    }

    $flag ? VoidProducer::act() : VoidProducer::act();
    $flag || VoidProducer::act(); // @mago-expect analysis:redundant-logical-operation
    $maybe ?? VoidProducer::act();

    match ($flag) {
        true => VoidProducer::act(),
        false => VoidProducer::act(),
    };
}

/**
 * @return Closure(): void
 */
function void_result_from_arrow_function(): Closure
{
    return fn() => VoidProducer::act();
}
