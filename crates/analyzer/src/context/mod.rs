use mago_allocator::Arena;
use mago_word::Word;
use mago_word::WordMap;
use mago_word::WordSet;

use mago_codex::metadata::CodebaseMetadata;
use mago_codex::metadata::class_like::ClassLikeMetadata;
use mago_codex::reference::SymbolReferences;
use mago_codex::ttype::resolution::TypeResolutionContext;
use mago_collector::Collector;
use mago_database::file::File;
use mago_names::ResolvedNames;
use mago_names::scope::NamespaceScope;
use mago_phpdoc_syntax::PHPDocParser;
use mago_phpdoc_syntax::cst::Element;
use mago_reporting::Annotation;
use mago_reporting::Issue;
use mago_reporting::IssueCollection;
use mago_span::HasSpan;
use mago_span::Span;
use mago_syntax::comments::docblock::PrecedingDocblocks;
use mago_syntax::cst::Expression;
use mago_syntax::cst::Identifier;
use mago_syntax::cst::Trivia;

use crate::analysis_result::AnalysisResult;
use crate::artifacts::AnalysisArtifacts;
use crate::code::IssueCode;
use crate::context::assertion::AssertionContext;
use crate::context::block::BlockContext;
use crate::external::ExternalAnalysisSession;
use crate::plugin::PluginRegistry;
use crate::settings::Settings;

pub mod assertion;
pub mod block;
pub mod block_flags;
pub mod scope;
pub mod utils;

#[derive(Debug)]
#[allow(clippy::field_scoped_visibility_modifiers)]
#[allow(clippy::struct_field_names)]
pub struct Context<'ctx, 'arena, A>
where
    A: Arena,
{
    pub(super) arena: &'arena A,
    pub(super) codebase: &'ctx CodebaseMetadata,
    pub(super) source_file: &'ctx File,
    pub(super) resolved_names: &'ctx ResolvedNames<'arena>,
    pub(super) type_resolution_context: TypeResolutionContext,
    pub(super) comments: &'arena [Trivia<'arena>],
    pub(super) settings: &'ctx Settings,
    pub(super) scope: NamespaceScope,
    pub(super) collector: Collector<'ctx, 'arena, A>,
    pub(super) statement_span: Span,
    pub(super) plugin_registry: &'ctx PluginRegistry,
    pub(super) external_analysis_session: Option<&'ctx ExternalAnalysisSession>,
    pub(super) additional_symbol_references: Option<&'ctx SymbolReferences>,
    class_initializers: WordMap<WordSet>,
    /// Spans of the expressions whose value the language throws away, such as the
    /// expression of an expression statement or a `for` initializer/increment.
    ///
    /// Analysis is a recursive descent, so an enclosing construct registers the
    /// expression it is about to discard before descending into it, and unregisters
    /// it afterwards. Checks that only make sense for a value that is actually
    /// consumed - such as [`IssueCode::VoidResultUsed`] - consult this via
    /// [`Context::is_value_discarded`].
    value_discarding_expressions: Vec<Span>,
}

impl<'ctx, 'arena, A> Context<'ctx, 'arena, A>
where
    A: Arena,
{
    pub fn new(
        arena: &'arena A,
        codebase: &'ctx CodebaseMetadata,
        source: &'ctx File,
        resolved_names: &'ctx ResolvedNames<'arena>,
        settings: &'ctx Settings,
        statement_span: Span,
        comments: &'arena [Trivia<'arena>],
        collector: Collector<'ctx, 'arena, A>,
        plugin_registry: &'ctx PluginRegistry,
        external_analysis_session: Option<&'ctx ExternalAnalysisSession>,
        additional_symbol_references: Option<&'ctx SymbolReferences>,
    ) -> Self {
        Self {
            arena,
            codebase,
            source_file: source,
            resolved_names,
            type_resolution_context: TypeResolutionContext::new(),
            comments,
            settings,
            scope: NamespaceScope::default(),
            statement_span,
            collector,
            plugin_registry,
            external_analysis_session,
            additional_symbol_references,
            class_initializers: WordMap::default(),
            value_discarding_expressions: Vec::new(),
        }
    }

    /// Returns the current number of registered value-discarding expressions, to be
    /// handed back to [`Context::restore_value_discarding_depth`] once the enclosing
    /// construct has been analyzed.
    pub(crate) fn value_discarding_depth(&self) -> usize {
        self.value_discarding_expressions.len()
    }

    /// Registers `expression` as having its value discarded.
    ///
    /// The sub-expressions that merely supply `expression`'s value are discarded along
    /// with it, and are registered too. That covers the constructs PHP code routinely
    /// uses as statements for their side effects alone: `match (true) { $c => act() };`,
    /// `$c ? act() : otherwise();`, and `$c || act();`.
    pub(crate) fn register_value_discarding_expression(&mut self, expression: &Expression<'arena>) {
        self.value_discarding_expressions.push(expression.span());

        match expression {
            Expression::Parenthesized(parenthesized) => {
                self.register_value_discarding_expression(parenthesized.expression);
            }
            Expression::UnaryPrefix(unary) if unary.operator.is_error_control() => {
                self.register_value_discarding_expression(unary.operand);
            }
            Expression::Match(r#match) => {
                for arm in r#match.arms.iter() {
                    self.register_value_discarding_expression(arm.expression());
                }
            }
            Expression::Conditional(conditional) => {
                if let Some(then) = conditional.then {
                    self.register_value_discarding_expression(then);
                }

                self.register_value_discarding_expression(conditional.r#else);
            }
            // The left operand of these is still read, to decide whether to evaluate the right one.
            Expression::Binary(binary) if binary.operator.is_logical() || binary.operator.is_null_coalesce() => {
                self.register_value_discarding_expression(binary.rhs);
            }
            _ => {}
        }
    }

    /// Drops every expression registered since `depth` was taken.
    pub(crate) fn restore_value_discarding_depth(&mut self, depth: usize) {
        self.value_discarding_expressions.truncate(depth);
    }

    /// Returns `true` if the expression at `span` has its value discarded by the
    /// construct that encloses it, rather than consumed as a value.
    pub(crate) fn is_value_discarded(&self, span: Span) -> bool {
        self.value_discarding_expressions.contains(&span)
    }

    pub(crate) fn prepare_class_initializers(
        &mut self,
        class_like: &ClassLikeMetadata,
    ) -> Result<(), crate::error::AnalysisError> {
        if self.external_analysis_session.is_none() || !self.plugin_registry.may_have_class_initializer_provider() {
            return Ok(());
        }

        let classes =
            std::iter::once(class_like.name).chain(class_like.all_parent_classes.iter().copied()).collect::<Vec<_>>();
        for class in classes {
            if self.class_initializers.contains_key(&class) {
                continue;
            }

            let Some(metadata) = self.codebase.get_class_like(class.as_bytes()) else {
                continue;
            };
            let initializers =
                self.plugin_registry.get_class_initializers(self.codebase, metadata, self.external_analysis_session);
            self.class_initializers.insert(class, initializers);
        }

        Ok(())
    }

    #[inline]
    pub(crate) fn is_class_initializer_for(&self, metadata: &ClassLikeMetadata, method: Word) -> bool {
        self.settings.is_class_initializer_for(metadata, method)
            || self.class_initializers.get(&metadata.name).is_some_and(|methods| methods.contains(&method))
    }

    pub(crate) fn applicable_class_initializers<'meta>(
        &'meta self,
        metadata: &'meta ClassLikeMetadata,
    ) -> impl Iterator<Item = Word> + 'meta {
        self.settings
            .applicable_class_initializers(metadata)
            .chain(self.class_initializers.get(&metadata.name).into_iter().flat_map(|methods| methods.iter().copied()))
    }

    /// Resolves the correct function name based on PHP's dynamic name resolution rules.
    ///
    /// This function determines the fully qualified name (FQN) of a function being called,
    /// accounting for PHP's nuanced resolution rules:
    ///
    /// - If the function is explicitly imported via `use`, it resolves to the imported name.
    /// - If the function name starts with a leading `\`, it is treated as a global function.
    /// - If no `\` is present:
    ///   1. The function name is checked in the current namespace.
    ///   2. If not found, it falls back to the global namespace.
    ///   3. If neither exists, it defaults to the current namespace's FQN.
    ///
    /// # Arguments
    ///
    /// - `identifier`: The identifier representing the function name in the source code.
    ///
    /// # Returns
    ///
    /// - A reference to the resolved function name as a string.
    ///
    /// # Note
    ///
    /// Function names in PHP are case-insensitive; they are stored and looked up in lowercase
    /// within the codebase metadata.
    pub fn resolve_function_name<'ast>(&self, identifier: &'ast Identifier<'arena>) -> &'arena [u8] {
        if self.resolved_names.is_imported(identifier) {
            return self.resolved_names.get(identifier);
        }

        let name = identifier.value();

        if let Some(stripped) = name.strip_prefix(b"\\") {
            return stripped;
        }

        let fqfn = self.resolved_names.get(&identifier);
        if self.codebase.function_exists(fqfn) {
            return fqfn;
        }

        if !name.contains(&b'\\') && self.codebase.function_exists(name) {
            return name;
        }

        fqfn
    }

    pub fn get_assertion_context_from_block(
        &self,
        block_context: &BlockContext<'ctx>,
    ) -> AssertionContext<'ctx, 'arena, A> {
        self.get_assertion_context(block_context.scope.get_class_like_name())
    }

    #[inline]
    pub fn get_assertion_context(&self, this_class_name: Option<Word>) -> AssertionContext<'ctx, 'arena, A> {
        AssertionContext {
            arena: self.arena,
            resolved_names: self.resolved_names,
            codebase: self.codebase,
            this_class_name,
            trust_existence_checks: self.settings.trust_existence_checks,
        }
    }

    pub fn get_parsed_docblocks(&mut self) -> Vec<Element<'arena>> {
        let mut elements = vec![];
        for trivia in PrecedingDocblocks::new(self.comments, self.statement_span.start.offset) {
            let document = PHPDocParser::parse_with_span(self.arena, trivia.value, trivia.span);

            for error in document.errors {
                let error_span = error.span();

                let mut issue = Issue::error(error.to_string())
                    .with_annotation(
                        Annotation::primary(error_span).with_message("This part of the docblock has a syntax error"),
                    )
                    .with_note(error.note());

                if trivia.span != error_span {
                    issue = issue.with_annotation(
                        Annotation::secondary(trivia.span).with_message("The error is within this docblock"),
                    );
                }

                issue = issue.with_annotation(
                    Annotation::secondary(self.statement_span)
                        .with_message("This docblock is associated with the following statement"),
                );

                issue = issue.with_help(error.help());

                self.collector.report_with_code(IssueCode::InvalidDocblock, issue);
            }

            elements.extend(document.elements.iter().copied());
        }

        elements
    }

    pub fn record<T>(&mut self, callback: impl FnOnce(&mut Context<'ctx, 'arena, A>) -> T) -> (T, IssueCollection) {
        self.collector.start_recording();
        let result = callback(self);
        let issues = self.collector.finish_recording().unwrap_or_default();

        (result, issues)
    }

    pub fn finish(self, artifacts: AnalysisArtifacts, analysis_result: &mut AnalysisResult) {
        analysis_result.issues.extend(self.collector.finish());
        analysis_result.symbol_references.extend(artifacts.symbol_references);
    }

    /// Drain the collector into the analysis result and return any
    /// unreported issues. Used by [`crate::Analyzer::analyze_with_artifacts`]
    /// when the caller needs to retain ownership of [`AnalysisArtifacts`]
    /// after analysis completes.
    pub fn finish_collector(self, analysis_result: &mut AnalysisResult, defer_pragmas: bool) {
        if defer_pragmas {
            let (issues, pragmas) = self.collector.defer();
            analysis_result.issues.extend(issues);
            analysis_result.add_deferred_pragmas(pragmas);
        } else {
            analysis_result.issues.extend(self.collector.finish());
        }
    }
}
