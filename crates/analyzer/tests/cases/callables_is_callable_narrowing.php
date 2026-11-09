<?php

declare(strict_types=1);

function callables_maybe_call(mixed $val): mixed
{
    if (is_callable($val)) {
        return $val();
    }
    return null;
}

// @mago-expect analysis:invalid-type-cast
echo (string) callables_maybe_call('strlen');
