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
 * Every template parameter has a default, so writing none of them is already complete.
 *
 * @template T = string
 */
class Defaulted
{
    /** @var null|T */
    public mixed $value = null;
}

class NotGeneric
{
}

/**
 * Every position below leaves out template arguments that the referenced class requires.
 *
 * The pragmas live on the class rather than on each member because a report whose span points
 * into a docblock is not covered by a pragma inside that same docblock.
 *
 * @mago-expect analysis:missing-template-type(8)
 * @mago-expect analysis:missing-template-parameter
 * @mago-expect analysis:excess-template-parameter
 */
class BareGenerics
{
    public ?Collection $bareProperty = null;

    /** @var null|Collection */
    public ?Collection $bareDocblockProperty = null;

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

    /** @return Collection */
    public function bareDocblockReturn(): Collection
    {
        return new Collection();
    }

    /** A class with more than one template parameter is reported once, not once per parameter. */
    public function bareMultiTemplate(Mapping $m): void
    {
    }

    /**
     * A generic nested inside another type is walked into.
     *
     * @param list<Collection> $collections
     */
    public function nestedBare(array $collections): void
    {
    }

    /**
     * Some arguments, but not all of them, is wrong code rather than an omission, so it keeps the
     * error the inheritance check already uses.
     *
     * @param Mapping<string> $m
     */
    public function tooFewArguments(Mapping $m): void
    {
    }

    /**
     * @param Collection<int, string> $c
     */
    public function tooManyArguments(Collection $c): void
    {
    }
}

/**
 * Nothing in this class is missing anything, so nothing here may be reported.
 */
class ParameterizedGenerics
{
    /** @var null|Collection<int> */
    public ?Collection $preciseProperty = null;

    public ?NotGeneric $nonGenericProperty = null;

    public ?Defaulted $defaultedProperty = null;

    /** @param Collection<int> $c */
    public function preciseParameter(Collection $c): void
    {
    }

    /** @return Collection<int> */
    public function preciseReturn(): Collection
    {
        return new Collection();
    }

    /** @param list<Collection<int>> $collections */
    public function nestedPrecise(array $collections): void
    {
    }

    /** @param Mapping<string, int> $m */
    public function preciseMultiTemplate(Mapping $m): void
    {
    }

    /**
     * A class that declares no templates is complete when named bare.
     */
    public function nonGeneric(NotGeneric $plain): NotGeneric
    {
        return $plain;
    }

    /**
     * A class whose templates all have defaults is complete when named bare.
     */
    public function allDefaults(Defaulted $d): Defaulted
    {
        return $d;
    }
}

/**
 * Generic types from the prelude are checked the same way user-defined ones are.
 *
 * `Generator` is the interesting one: the type builder pads its argument list with synthesized
 * `mixed`s, so a bare `Generator` arrives carrying two arguments and `Generator<int, string>`
 * arrives carrying four. Only the arguments actually written in the source decide which is bare.
 *
 * @mago-expect analysis:missing-template-type(3)
 */
class PreludeGenerics
{
    public function iterator(\Iterator $i): void
    {
    }

    public function nativeTraversable(\Traversable $items): void
    {
    }

    /** @return \Generator */
    public function bareGenerator(): iterable
    {
        yield 1;
    }

    /** @return \Generator<int, string> */
    public function preciseGenerator(): iterable
    {
        yield 'a';
    }

    /**
     * `Iterator<TValue>` has its key type filled in, and is complete as written.
     *
     * @param \Iterator<string> $i
     */
    public function valueOnlyIterator(\Iterator $i): void
    {
    }
}

/**
 * The seam with `imprecise-type`.
 *
 * A bare `@param Traversable` currently silences `imprecise-type` on the native `iterable` hint
 * even though it says nothing about the value type either. It is a bare generic like any other, so
 * it is reported here.
 *
 * @mago-expect analysis:missing-template-type
 */
class ImpreciseTypeSeam
{
    /** @param \Traversable $items */
    public function docblockTraversable(iterable $items): void
    {
    }
}

/**
 * The arity of a template's own constraint belongs to the `@template` line that declares it, not to
 * every signature that mentions the template. `Mapping<TValue>` below is one argument short, and
 * that is still not reported against the three positions that name `TMapping`.
 *
 * Checking the `@template` line itself is left for a follow-up.
 *
 * @template TValue
 * @template TMapping of Mapping<TValue>
 */
class TemplateConstraints
{
    /** @var null|TMapping */
    public mixed $mapping = null;

    /**
     * @param TMapping $mapping
     *
     * @return TMapping
     */
    public function round(mixed $mapping): mixed
    {
        return $mapping;
    }
}

/**
 * A generic class referring to itself is left alone: `self` is indistinguishable from naming the
 * class bare by the time the check runs, and `: self` in a generic class is not a mistake.
 *
 * @template T
 */
class SelfReferences
{
    /** @var list<T> */
    public array $items = [];

    public function itself(): self
    {
        return $this;
    }

    public function latest(): static
    {
        return $this;
    }

    /** @return $this */
    public function fluent(): static
    {
        return $this;
    }

    public function sibling(self $other): self
    {
        return $other;
    }
}
