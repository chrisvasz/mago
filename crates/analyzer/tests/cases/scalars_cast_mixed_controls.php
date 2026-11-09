<?php

declare(strict_types=1);

function cast_mixed_to_float(mixed $value): float
{
    // @mago-expect analysis:invalid-type-cast
    return (float) $value;
}

/**
 * @return array<array-key, mixed>
 */
function cast_mixed_to_array(mixed $value): array
{
    // @mago-expect analysis:invalid-type-cast
    return (array) $value;
}

function cast_mixed_to_bool(mixed $value): bool
{
    // @mago-expect analysis:mixed-operand
    return (bool) $value;
}

function concat_mixed(mixed $value): string
{
    // @mago-expect analysis:mixed-operand
    return 'value: ' . $value;
}

function echo_mixed(mixed $value): void
{
    // @mago-expect analysis:mixed-argument
    echo $value;
}
