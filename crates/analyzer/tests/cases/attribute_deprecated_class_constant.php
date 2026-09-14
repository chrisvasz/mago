<?php

declare(strict_types=1);

final class AttributedConstants
{
    #[\Deprecated(message: 'Use AttributedConstants::NEW_MODE instead.')]
    public const OLD_MODE = 1;

    #[\Deprecated]
    public const BARE_MODE = 2;

    public const NEW_MODE = 3;
}

/**
 * @mago-expect analysis:deprecated-class-constant
 */
function read_attributed_constant(): int
{
    return AttributedConstants::OLD_MODE;
}

/**
 * @mago-expect analysis:deprecated-class-constant
 */
function read_bare_attributed_constant(): int
{
    return AttributedConstants::BARE_MODE;
}

function read_live_attributed_constant(): int
{
    return AttributedConstants::NEW_MODE;
}
