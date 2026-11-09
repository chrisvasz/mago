<?php

declare(strict_types=1);

function cast_mixed_to_string(mixed $value): string
{
    // @mago-expect analysis:invalid-type-cast
    return (string) $value;
}

function cast_mixed_to_int(mixed $value): int
{
    // @mago-expect analysis:invalid-type-cast
    return (int) $value;
}

function interpolate_mixed(mixed $value): string
{
    // @mago-expect analysis:invalid-type-cast
    return "value: $value";
}

function interpolate_mixed_braced(mixed $value): string
{
    // @mago-expect analysis:invalid-type-cast
    return "value: {$value}";
}
