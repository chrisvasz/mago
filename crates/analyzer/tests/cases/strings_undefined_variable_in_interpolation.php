<?php

declare(strict_types=1);

function probe(): string
{
    /** @mago-expect analysis:undefined-variable */
    /** @mago-expect analysis:invalid-type-cast */
    return "value: {$undefined}";
}
