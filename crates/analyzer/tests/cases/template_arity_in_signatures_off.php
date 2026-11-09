<?php

declare(strict_types=1);

namespace Fixture;

/**
 * @template T
 */
class Collection
{
    /** @var list<T> */
    public array $items = [];
}

/**
 * @template TKey of array-key
 * @template TValue
 */
interface Mapping
{
    /**
     * @param TKey $key
     *
     * @return TValue
     */
    public function get(int|string $key): mixed;
}

/**
 * Without `check_missing_type_hints`, leaving the template arguments out entirely is silent — it
 * is an omission, and reporting it by default would fire on most real-world signatures.
 */
class BareGenericsAreSilent
{
    public ?Collection $bareProperty = null;

    public function bareParameter(Collection $c): void
    {
    }

    /** @param Collection $c */
    public function bareDocblockParameter(Collection $c): void
    {
    }

    public function bareReturn(): Collection
    {
        return new Collection();
    }

    public function bareMultiTemplate(Mapping $m): void
    {
    }
}

class NotGeneric
{
}

/**
 * Providing the wrong number of arguments is wrong code rather than an omission, so it is reported
 * whether or not `check_missing_type_hints` is on.
 *
 * @mago-expect analysis:missing-template-parameter
 * @mago-expect analysis:excess-template-parameter(2)
 */
class WrongArityStillReports
{
    /** @param NotGeneric<int> $x */
    public function argumentsOnNonGenericClass(NotGeneric $x): void
    {
    }

    /** @param Mapping<string> $m */
    public function tooFewArguments(Mapping $m): void
    {
    }

    /** @param Collection<int, string> $c */
    public function tooManyArguments(Collection $c): void
    {
    }
}
