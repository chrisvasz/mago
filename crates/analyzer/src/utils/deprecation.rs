use mago_allocator::Arena;
use mago_codex::metadata::class_like_constant::ClassLikeConstantMetadata;
use mago_codex::metadata::property::PropertyMetadata;
use mago_reporting::Annotation;
use mago_reporting::Issue;
use mago_span::Span;
use mago_word::Word;

use crate::code::IssueCode;
use crate::context::Context;

/// Reports a fetch of a class constant marked with `@deprecated` or `#[\Deprecated]`.
///
/// `display_name` is rendered verbatim into the message — pass the user's original casing
/// (e.g. `Legacy::OLD_C`).
pub fn check_class_constant_deprecation<A>(
    context: &mut Context<'_, '_, A>,
    metadata: &ClassLikeConstantMetadata,
    display_name: &(impl std::fmt::Display + ?Sized),
    span: Span,
) where
    A: Arena,
{
    if !metadata.flags.is_deprecated() {
        return;
    }

    report_deprecated_member(
        context,
        IssueCode::DeprecatedClassConstant,
        "class constant",
        display_name,
        metadata.deprecation_message,
        span,
    );
}

/// Reports a read from, or a write to, a property marked with `@deprecated`.
///
/// `display_name` is rendered verbatim into the message — pass the user's original casing
/// (e.g. `Legacy::$oldProp`).
pub fn check_property_deprecation<A>(
    context: &mut Context<'_, '_, A>,
    metadata: &PropertyMetadata,
    display_name: &(impl std::fmt::Display + ?Sized),
    span: Span,
) where
    A: Arena,
{
    if !metadata.flags.is_deprecated() {
        return;
    }

    report_deprecated_member(
        context,
        IssueCode::DeprecatedProperty,
        "property",
        display_name,
        metadata.deprecation_message,
        span,
    );
}

fn report_deprecated_member<A>(
    context: &mut Context<'_, '_, A>,
    code: IssueCode,
    kind: &str,
    display_name: &(impl std::fmt::Display + ?Sized),
    message: Option<Word>,
    span: Span,
) where
    A: Arena,
{
    let note = match message {
        Some(message) => format!("The {kind} `{display_name}` is marked as deprecated: {message}"),
        None => format!(
            "The {kind} `{display_name}` is marked as deprecated and may be removed or its behavior changed in future versions."
        ),
    };

    context.collector.report_with_code(
        code,
        Issue::warning(format!("Using deprecated {kind}: `{display_name}`."))
            .with_annotation(Annotation::primary(span).with_message(format!("This {kind} is deprecated")))
            .with_note(note)
            .with_help(format!(
                "Consult the documentation for `{display_name}` for alternatives or migration instructions."
            )),
    );
}
