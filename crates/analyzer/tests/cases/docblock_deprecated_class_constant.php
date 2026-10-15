<?php

declare(strict_types=1);

final class LegacyConstants
{
    /**
     * @deprecated Use LegacyConstants::NEW_LIMIT instead.
     */
    public const OLD_LIMIT = 1;

    public const NEW_LIMIT = 2;

    /**
     * @deprecated
     */
    public const UNDOCUMENTED = 3;

    /**
     * @deprecated Scheduled for removal.
     * @not-deprecated
     */
    public const REINSTATED = 4;
}

/**
 * @mago-expect analysis:deprecated-class-constant
 */
function read_deprecated_constant(): int
{
    return LegacyConstants::OLD_LIMIT;
}

/**
 * @mago-expect analysis:deprecated-class-constant
 */
function read_deprecated_constant_without_description(): int
{
    return LegacyConstants::UNDOCUMENTED;
}

function read_reinstated_constant(): int
{
    return LegacyConstants::REINSTATED;
}

function read_live_constant(): int
{
    return LegacyConstants::NEW_LIMIT;
}
