<?php

declare(strict_types=1);

/** @param array<string,mixed> $meta */
function kind_human_value(array $meta, mixed $value): string
{
    /** @mago-expect analysis:mixed-assignment */
    /** @mago-expect analysis:possibly-undefined-string-array-index */
    $kind = $meta['kind'];
    $fn = "kind_human_value_{$kind}";
    if (!function_exists($fn)) {
        /** @mago-expect analysis:invalid-type-cast */
        return (string) $value;
    }

    /** @mago-expect analysis:invalid-type-cast */
    return (string) $fn($meta, $value);
}
