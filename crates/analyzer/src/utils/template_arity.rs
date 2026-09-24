use mago_allocator::Arena;
use mago_codex::metadata::ttype::TypeMetadata;
use mago_codex::ttype::TType;
use mago_codex::ttype::TypeRef;
use mago_codex::ttype::atomic::TAtomic;
use mago_codex::ttype::atomic::object::TObject;
use mago_codex::ttype::atomic::object::named::TNamedObject;
use mago_codex::ttype::union::TUnion;
use mago_reporting::Annotation;
use mago_reporting::Issue;
use mago_span::Span;
use mago_word::Word;

use crate::code::IssueCode;
use crate::context::Context;

/// A single generic class reference whose template argument count does not line up with the
/// referenced class.
struct ArityMismatch {
    /// The referenced class, interface, or enum.
    name: Word,
    /// Where that class is declared, for the secondary annotation.
    declaration_span: Span,
    /// How many template parameters the class declares in total.
    expected: usize,
    /// How many of those have no default, and so must be provided.
    min_required: usize,
    /// How many template arguments the reference actually provides.
    actual: usize,
}

/// Report generic class references in a signature position that carry the wrong number of
/// template arguments.
///
/// `check_template_parameters` in [`crate::statement::class_like`] performs the same check where a
/// class inherits from a generic type; this covers the same mismatch where a generic class is
/// named as a parameter, return, or property type.
///
/// The three outcomes are deliberately not treated alike:
///
/// - No arguments at all is an omission rather than a mistake — `Collection $items` is legal PHP
///   and pervasive — so it is a [`IssueCode::MissingTemplateType`] warning, gated behind
///   `check_missing_type_hints` alongside the other "you could say more here" checks.
/// - Some, but not enough, arguments is wrong code, and keeps the
///   [`IssueCode::MissingTemplateParameter`] error the inheritance check uses.
/// - Too many arguments is likewise wrong code, and keeps [`IssueCode::ExcessTemplateParameter`].
pub fn report_template_arity_mismatches<A>(
    context: &mut Context<'_, '_, A>,
    type_metadata: &TypeMetadata,
    enclosing_class_like: Option<Word>,
    position: &str,
) where
    A: Arena,
{
    if type_metadata.inferred {
        return;
    }

    for mismatch in collect_arity_mismatches(context, type_metadata, enclosing_class_like) {
        report_arity_mismatch(context, &mismatch, type_metadata.span, position);
    }
}

/// Collect every named object written at this position, including the ones nested inside unions,
/// intersections, `class-string<...>`, and other generics.
///
/// The constraint of a generic parameter is deliberately not walked into: an `@template T of
/// Foo<A>` whose arity is wrong belongs to the `@template` line that declares it, not to every
/// signature that happens to mention `T`.
fn collect_named_objects(type_union: &TUnion) -> Vec<&TNamedObject> {
    let mut named_objects = vec![];
    let mut nodes = type_union.get_child_nodes();

    while let Some(node) = nodes.pop() {
        if let TypeRef::Atomic(atomic) = node {
            if matches!(atomic, TAtomic::GenericParameter(_)) {
                continue;
            }

            if let TAtomic::Object(TObject::Named(named_object)) = atomic {
                named_objects.push(named_object);
            }
        }

        nodes.extend(match node {
            TypeRef::Union(union) => union.get_child_nodes(),
            TypeRef::Atomic(atomic) => atomic.get_child_nodes(),
        });
    }

    named_objects
}

/// Walk every named object in `type_metadata` and keep the ones whose arity is off.
fn collect_arity_mismatches<A>(
    context: &Context<'_, '_, A>,
    type_metadata: &TypeMetadata,
    enclosing_class_like: Option<Word>,
) -> Vec<ArityMismatch>
where
    A: Arena,
{
    let mut mismatches = vec![];

    for named_object in collect_named_objects(&type_metadata.type_union) {
        // `static` and `$this` inherit the enclosing class's template arguments; the author never
        // spells them out, so there is nothing to be missing here.
        if named_object.is_static || named_object.is_this {
            continue;
        }

        // `self` resolves to the enclosing class with no arguments, exactly like naming that class
        // bare would. The two are indistinguishable by this point, so a class referring to itself
        // is left alone rather than reporting every `: self` in a generic class.
        if enclosing_class_like
            .is_some_and(|class_like| class_like.as_bytes().eq_ignore_ascii_case(named_object.name.as_bytes()))
        {
            continue;
        }

        let Some(class_like_metadata) = context.codebase.get_class_like(named_object.name.as_bytes()) else {
            // Unknown classes are already reported as `non-existent-class-like`.
            continue;
        };

        let expected = class_like_metadata.template_types.len();
        let min_required = class_like_metadata.template_types.values().take_while(|t| t.default.is_none()).count();

        // The type builder pads the argument list of the iterator-shaped prelude types with
        // synthesized `mixed`s, so `Generator<int, string>` arrives here already carrying four
        // arguments and a bare `Generator` already carrying two. Only the arguments the author
        // actually wrote decide whether this is a bare usage; the padded length decides arity.
        let provided = named_object.type_parameters.as_deref().unwrap_or_default();
        let written = provided.iter().filter(|parameter| !parameter.from_template_fallback()).count();
        let actual = if written == 0 { 0 } else { provided.len() };

        if actual == 0 && min_required == 0 {
            // Either not a generic class at all, or every template parameter has a default.
            continue;
        }

        if actual >= min_required && actual <= expected {
            continue;
        }

        // A union can name the same class more than once — `Mapping<Collection, Collection>` — and
        // every report would land on the same span, so say it once.
        if mismatches.iter().any(|m: &ArityMismatch| m.name == named_object.name && m.actual == actual) {
            continue;
        }

        mismatches.push(ArityMismatch {
            name: named_object.name,
            declaration_span: class_like_metadata.name_span.unwrap_or(class_like_metadata.span),
            expected,
            min_required,
            actual,
        });
    }

    mismatches
}

fn report_arity_mismatch<A>(context: &mut Context<'_, '_, A>, mismatch: &ArityMismatch, span: Span, position: &str)
where
    A: Arena,
{
    let ArityMismatch { name, declaration_span, expected, min_required, actual } = mismatch;

    let declaration_annotation = Annotation::secondary(*declaration_span)
        .with_message(format!("`{name}` is declared with {expected} template parameter(s)"));

    if *actual > *expected {
        context.collector.report_with_code(
            IssueCode::ExcessTemplateParameter,
            Issue::error(format!("Too many template arguments for `{name}`: expected {expected}, but found {actual}."))
                .with_annotation(
                    Annotation::primary(span)
                        .with_message(format!("{actual} template arguments are provided for `{name}` here")),
                )
                .with_annotation(declaration_annotation)
                .with_help(format!("Remove the extra template arguments from `{name}` in {position}.")),
        );

        return;
    }

    if *actual > 0 {
        context.collector.report_with_code(
            IssueCode::MissingTemplateParameter,
            Issue::error(format!(
                "Too few template arguments for `{name}`: expected at least {min_required}, but found {actual}."
            ))
            .with_annotation(
                Annotation::primary(span)
                    .with_message(format!("Only {actual} template argument(s) are provided for `{name}` here")),
            )
            .with_annotation(declaration_annotation)
            .with_help(format!("Provide all {min_required} required template arguments for `{name}` in {position}.")),
        );

        return;
    }

    // A bare generic is an omission rather than an error, and far too common to report by
    // default; it rides along with the other `check_missing_type_hints` diagnostics.
    if !context.settings.check_missing_type_hints {
        return;
    }

    context.collector.report_with_code(
        IssueCode::MissingTemplateType,
        Issue::warning(format!("Generic class `{name}` is used in {position} without its template arguments."))
            .with_annotation(
                Annotation::primary(span).with_message(format!("`{name}` does not specify its template arguments")),
            )
            .with_annotation(declaration_annotation)
            .with_note(
                "A bare generic type says nothing about what it holds, so the analyzer has to fall back to the template constraints and cannot check how the value is used.",
            )
            .with_help(format!(
                "Specify the {min_required} required template argument(s) in a docblock, for example `{name}<...>`."
            )),
    );
}
