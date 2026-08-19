<?php

declare(strict_types=1);

function castMixedToString(mixed $value): string
{
    /** @mago-expect analysis:invalid-type-cast */
    return (string) $value;
}

function castIntToString(int $value): string
{
    return (string) $value;
}
