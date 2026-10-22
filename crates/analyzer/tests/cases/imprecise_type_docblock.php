<?php

declare(strict_types=1);

namespace Fixture;

class ImpreciseTypeDocblock
{
    /** @mago-expect analysis:imprecise-type */
    public array $bare = [];

    /**
     * @var array
     *
     * @mago-expect analysis:imprecise-type
     */
    public array $uselessDocblock = [];

    /** @var array<string, int> */
    public array $preciseDocblock = [];

    /** @var list<self> */
    public array $preciseListDocblock = [];

    /** @var array<array-key, mixed> */
    public array $explicitEquivalentDocblock = [];

    /** @mago-expect analysis:imprecise-type(2) */
    public function bareHints(array $a): array
    {
        return $a;
    }

    /**
     * A `@param` that repeats the native hint leaves both positions imprecise.
     *
     * @param array $a
     *
     * @mago-expect analysis:imprecise-type(2)
     */
    public function uselessParameterDocblock(array $a): array
    {
        return $a;
    }

    /**
     * @param array $a
     * @return array
     *
     * @mago-expect analysis:imprecise-type(2)
     */
    public function uselessDocblocks(array $a): array
    {
        return $a;
    }

    /**
     * @param ?array $a
     *
     * @mago-expect analysis:imprecise-type(2)
     */
    public function uselessNullableParameterDocblock(?array $a): array
    {
        return $a ?? [];
    }

    /**
     * @param array<string, int> $a
     *
     * @return list<int>
     */
    public function preciseDocblocks(array $a): array
    {
        return [];
    }

    /**
     * @param list<self> $a
     *
     * @return array<string, list<int>>
     */
    public function preciseListDocblocks(array $a): array
    {
        return [];
    }

    /**
     * Spelling the equivalent out explicitly is what the report suggests, so it keeps
     * silencing both positions.
     *
     * @param array<array-key, mixed> $a
     *
     * @return array<array-key, mixed>
     */
    public function explicitEquivalentDocblocks(array $a): array
    {
        return $a;
    }

    /**
     * @param iterable $items
     *
     * @mago-expect analysis:imprecise-type
     */
    public function uselessIterableDocblock(iterable $items): void
    {
    }

    /**
     * @param iterable<int, string> $items
     */
    public function preciseIterableDocblock(iterable $items): void
    {
    }
}
