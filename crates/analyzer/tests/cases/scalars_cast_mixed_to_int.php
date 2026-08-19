<?php

declare(strict_types=1);

function castMixedToInt(mixed $value): int
{
    /** @mago-expect analysis:invalid-type-cast */
    return (int) $value;
}

function castStringToInt(string $value): int
{
    return (int) $value;
}
