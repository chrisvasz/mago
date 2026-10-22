<?php

declare(strict_types=1);

final class LegacyProperties
{
    /**
     * @deprecated Use LegacyProperties::$newCount instead.
     */
    public int $oldCount = 1;

    public int $newCount = 2;

    /**
     * @deprecated Use LegacyProperties::$newTotal instead.
     */
    public static int $oldTotal = 3;

    /**
     * @deprecated Use LegacyProperties::newTotal() instead.
     */
    public function oldTotal(): int
    {
        return 5;
    }
}

/**
 * @mago-expect analysis:deprecated-property
 */
function read_deprecated_property(LegacyProperties $subject): int
{
    return $subject->oldCount;
}

/**
 * @mago-expect analysis:deprecated-property
 */
function write_deprecated_property(LegacyProperties $subject): void
{
    $subject->oldCount = 4;
}

/**
 * @mago-expect analysis:deprecated-property
 */
function read_deprecated_static_property(): int
{
    return LegacyProperties::$oldTotal;
}

/**
 * Control for the invocation path, which already honored `@deprecated` before the property and
 * class-constant paths were wired up.
 *
 * @mago-expect analysis:deprecated-method
 */
function call_deprecated_method(LegacyProperties $subject): int
{
    return $subject->oldTotal();
}

function read_live_property(LegacyProperties $subject): int
{
    return $subject->newCount;
}
