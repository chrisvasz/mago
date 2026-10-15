<?php

declare(strict_types=1);

function returns_void(): void
{
}

/**
 * @mago-expect analysis:void-result-used
 * @mago-expect analysis:impossible-condition
 */
if (returns_void()) {
    echo 1;
}
