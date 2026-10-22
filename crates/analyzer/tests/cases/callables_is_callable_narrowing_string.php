<?php

declare(strict_types=1);

function callables_call_if_callable(string $name): mixed
{
    if (is_callable($name)) {
        return $name('hello');
    }
    return null;
}

// @mago-expect analysis:invalid-type-cast
echo (string) callables_call_if_callable('strlen');
