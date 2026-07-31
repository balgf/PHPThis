<?php

declare(strict_types=1);

namespace PHPThis\Verification;

final class EnvironmentAccessProfile
{
    private const ENVIRONMENT_FUNCTIONS = [
        'apache_getenv',
        'apache_setenv',
        'getenv',
        'putenv',
    ];

    /** @var array<string, list<int>> */
    private const CALLABLE_ARGUMENT_POSITIONS = [
        'array_filter' => [2],
        'array_map' => [1],
        'array_reduce' => [2],
        'array_walk' => [2],
        'array_walk_recursive' => [2],
        'call_user_func' => [1],
        'call_user_func_array' => [1],
        'forward_static_call' => [1],
        'forward_static_call_array' => [1],
        'fromcallable' => [1],
        'iterator_apply' => [2],
        'ob_start' => [1],
        'pcntl_signal' => [2],
        'preg_replace_callback' => [2],
        'register_shutdown_function' => [1],
        'register_tick_function' => [1],
        'set_error_handler' => [1],
        'set_exception_handler' => [1],
        'spl_autoload_register' => [1],
        'uasort' => [2],
        'uksort' => [2],
        'usort' => [2],
    ];

    /** @var array<string, list<string>> */
    private const CALLABLE_ARGUMENT_NAMES = [
        'array_filter' => ['callback'],
        'array_map' => ['callback'],
        'array_reduce' => ['callback'],
        'array_walk' => ['callback'],
        'array_walk_recursive' => ['callback'],
        'call_user_func' => ['callback'],
        'call_user_func_array' => ['callback'],
        'forward_static_call' => ['callback'],
        'forward_static_call_array' => ['callback'],
        'fromcallable' => ['callback'],
        'iterator_apply' => ['callback'],
        'ob_start' => ['callback'],
        'pcntl_signal' => ['handler'],
        'preg_replace_callback' => ['callback'],
        'register_shutdown_function' => ['callback'],
        'register_tick_function' => ['callback'],
        'set_error_handler' => ['callback'],
        'set_exception_handler' => ['callback'],
        'spl_autoload_register' => ['callback'],
        'uasort' => ['callback'],
        'uksort' => ['callback'],
        'usort' => ['callback'],
    ];

    /**
     * @return array{reads: list<int>, failures: list<string>}
     */
    public static function inspect(string $contents, string $relativePath): array
    {
        $tokens = token_get_all($contents);
        $callableScopes = self::callableScopes($tokens);
        $variableOccurrences = self::variableOccurrences($tokens);
        $inputEnvBindings = self::inputEnvBindings($tokens);
        $reads = [];
        $failures = [];
        $line = 1;
        $attributeBracketDepth = 0;
        $attributeParenthesisDepth = 0;
        $importPending = false;
        $insideFunctionImport = false;
        $insideConstantImport = false;

        foreach ($tokens as $index => $token) {
            $tokenId = is_array($token) ? $token[0] : null;
            $tokenText = is_array($token) ? $token[1] : $token;
            $tokenLine = is_array($token) ? $token[2] : $line;

            if ($tokenId === T_ATTRIBUTE) {
                $attributeBracketDepth = 1;
                $attributeParenthesisDepth = 0;
                $line += substr_count($tokenText, "\n");
                continue;
            }

            if ($attributeBracketDepth > 0) {
                if (
                    $attributeParenthesisDepth > 0
                    && in_array($tokenId, [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                    && self::isInputEnvConstantName($tokenText)
                    && self::isGlobalConstantReference(
                        $tokens,
                        $index,
                        self::hasSafeInputEnvBinding($inputEnvBindings, $index),
                    )
                ) {
                    $failures[] = "PHT007 {$relativePath}:{$tokenLine} uses INPUT_ENV; read exact keys with \\getenv in the single application configuration boundary.";
                }

                if ($tokenText === '(') {
                    $attributeParenthesisDepth++;
                } elseif ($tokenText === ')') {
                    $attributeParenthesisDepth--;
                } elseif ($tokenText === '[') {
                    $attributeBracketDepth++;
                } elseif ($tokenText === ']') {
                    $attributeBracketDepth--;
                }

                $line += substr_count($tokenText, "\n");
                continue;
            }

            $isSignificant = !is_array($token)
                || !in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);

            if ($importPending && $isSignificant) {
                $insideFunctionImport = $tokenId === T_FUNCTION;
                $insideConstantImport = $tokenId === T_CONST;
                $importPending = false;
            }

            if ($tokenId === T_USE) {
                $importPending = true;
            } elseif ($tokenText === ';') {
                $insideFunctionImport = false;
                $insideConstantImport = false;
            }

            if ($tokenId === T_VARIABLE && $tokenText === '$_ENV') {
                $failures[] = "PHT007 {$relativePath}:{$tokenLine} reads \$_ENV; read exact keys with \\getenv in the single application configuration boundary.";
            }

            if ($tokenId === T_VARIABLE && $tokenText === '$_SERVER') {
                if (self::nextSignificantTokenText($tokens, $index + 1) === '[') {
                    $failures[] = "PHT007 {$relativePath}:{$tokenLine} indexes \$_SERVER; pass the HTTP transport array unchanged or read configuration with \\getenv in the single configuration boundary.";
                } elseif (!self::isCanonicalServerTransportHandoff($tokens, $index, $relativePath)) {
                    $failures[] = "PHT007 {$relativePath}:{$tokenLine} reads bare \$_SERVER outside the canonical front-controller transport handoff; pass exactly \$_SERVER, \$_GET, \$_POST, and \$_FILES to the terminal coordinator or use \\getenv in the configuration boundary.";
                }
            }

            if ($insideConstantImport) {
                if (
                    in_array(
                        $tokenId,
                        [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                        true,
                    )
                    && self::isInputEnvConstantName($tokenText)
                    && !self::isImportAlias($tokens, $index)
                    && !self::isGroupedImportMember($tokens, $index)
                ) {
                    $failures[] = "PHT007 {$relativePath}:{$tokenLine} imports INPUT_ENV; process environment access must use direct \\getenv calls in the single application configuration boundary.";
                }
            } elseif (
                in_array($tokenId, [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                && self::isInputEnvConstantName($tokenText)
                && self::isGlobalConstantReference(
                    $tokens,
                    $index,
                    self::hasSafeInputEnvBinding($inputEnvBindings, $index),
                )
            ) {
                $failures[] = "PHT007 {$relativePath}:{$tokenLine} uses INPUT_ENV; read exact keys with \\getenv in the single application configuration boundary.";
            }

            if (
                !in_array(
                    $tokenId,
                    [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                    true,
                )
            ) {
                if ($tokenId === T_CONSTANT_ENCAPSED_STRING) {
                    $indirectFunction = self::literalEnvironmentFunction($tokenText);

                    if (
                        $indirectFunction !== null
                        && self::isLiteralCallableReference(
                            $tokens,
                            $callableScopes,
                            $variableOccurrences,
                            $index,
                        )
                    ) {
                        $failures[] = "PHT007 {$relativePath}:{$tokenLine} references environment function {$indirectFunction} indirectly; use direct \\getenv calls only.";
                    }

                    if (
                        self::isInputEnvConstantName(self::decodedLiteral($tokenText))
                        && self::isConstantLookupArgument($tokens, $index)
                    ) {
                        $failures[] = "PHT007 {$relativePath}:{$tokenLine} resolves INPUT_ENV indirectly; process environment is read-only through direct \\getenv calls.";
                    }
                }

                $line += substr_count($tokenText, "\n");
                continue;
            }

            $functionName = self::environmentFunctionName($tokenText);

            if ($functionName === null) {
                $line += substr_count($tokenText, "\n");
                continue;
            }

            if ($insideFunctionImport) {
                $failures[] = "PHT007 {$relativePath}:{$tokenLine} imports environment function {$functionName}; use direct \\getenv calls only.";
                $line += substr_count($tokenText, "\n");
                continue;
            }

            if (!self::isFunctionCall($tokens, $index)) {
                $line += substr_count($tokenText, "\n");
                continue;
            }

            if ($functionName !== 'getenv') {
                $failures[] = "PHT007 {$relativePath}:{$tokenLine} calls environment function {$functionName}; process environment is read-only through direct \\getenv calls.";
                $line += substr_count($tokenText, "\n");
                continue;
            }

            $reads[] = $tokenLine;

            if ($tokenId !== T_NAME_FULLY_QUALIFIED || $tokenText !== '\\getenv') {
                $failures[] = "PHT007 {$relativePath}:{$tokenLine} calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').";
                $line += substr_count($tokenText, "\n");
                continue;
            }

            if (!self::hasValidGetenvArgument($tokens, $index)) {
                $failures[] = "PHT007 {$relativePath}:{$tokenLine} must call \\getenv with exactly one non-empty uppercase literal key of at most 128 bytes.";
            }

            $line += substr_count($tokenText, "\n");
        }

        return ['reads' => $reads, 'failures' => $failures];
    }

    /**
     * @param array<string, list<int>> $readsByFile
     * @return list<string>
     */
    public static function boundaryFailures(array $readsByFile): array
    {
        foreach ($readsByFile as $relativePath => $lines) {
            if ($lines === []) {
                unset($readsByFile[$relativePath]);
            }
        }

        if (count($readsByFile) <= 1) {
            return [];
        }

        ksort($readsByFile);
        $failures = [];

        foreach ($readsByFile as $relativePath => $lines) {
            $failures[] = sprintf(
                'PHT007 %s:%d reads process environment in more than one application-owned PHP file; centralize every \\getenv call in one configuration boundary.',
                $relativePath,
                $lines[0],
            );
        }

        return $failures;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function hasValidGetenvArgument(array $tokens, int $functionIndex): bool
    {
        $openIndex = self::nextSignificantTokenIndex($tokens, $functionIndex + 1);

        if ($openIndex === null || self::tokenText($tokens[$openIndex]) !== '(') {
            return false;
        }

        $arguments = [];
        $parenthesisDepth = 1;

        for ($index = $openIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            $tokenText = self::tokenText($token);

            if ($tokenText === '(') {
                $parenthesisDepth++;
            } elseif ($tokenText === ')') {
                $parenthesisDepth--;

                if ($parenthesisDepth === 0) {
                    break;
                }
            }

            if (
                !is_array($token)
                || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $arguments[] = $token;
            }
        }

        if ($parenthesisDepth !== 0 || count($arguments) !== 1) {
            return false;
        }

        $argument = $arguments[0];

        return is_array($argument)
            && $argument[0] === T_CONSTANT_ENCAPSED_STRING
            && preg_match('/\A([\'"])[A-Z][A-Z0-9_]{0,127}\1\z/D', $argument[1]) === 1;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isFunctionCall(array $tokens, int $index): bool
    {
        if (self::nextSignificantTokenText($tokens, $index + 1) !== '(') {
            return false;
        }

        $previousIndex = self::previousSignificantTokenIndex($tokens, $index - 1);
        $previous = $previousIndex === null ? null : $tokens[$previousIndex];
        $previousTokenId = is_array($previous) ? $previous[0] : null;

        return !in_array(
            $previousTokenId,
            [
                T_ATTRIBUTE,
                T_FUNCTION,
                T_NEW,
                T_OBJECT_OPERATOR,
                T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON,
            ],
            true,
        );
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isGlobalConstantReference(
        array $tokens,
        int $index,
        bool $hasSafeBareBinding,
    ): bool
    {
        $token = $tokens[$index];

        if (
            is_array($token)
            && $token[0] === T_STRING
            && $hasSafeBareBinding
        ) {
            return false;
        }

        if (self::isFunctionCall($tokens, $index)) {
            return false;
        }

        $previousIndex = self::previousSignificantTokenIndex($tokens, $index - 1);
        $previous = $previousIndex === null ? null : $tokens[$previousIndex];
        $previousTokenId = is_array($previous) ? $previous[0] : null;
        $previousText = $previous === null ? null : self::tokenText($previous);
        $nextIndex = self::nextSignificantTokenIndex($tokens, $index + 1);
        $next = $nextIndex === null ? null : $tokens[$nextIndex];
        $nextTokenId = is_array($next) ? $next[0] : null;
        $nextText = $next === null ? null : self::tokenText($next);
        $isNamedArgumentOrLabel = $nextText === ':'
            && (
                in_array($previousText, ['(', ',', '{', ';'], true)
                || $previousTokenId === T_OPEN_TAG
            );
        $isEnumCaseDeclaration = self::isEnumCaseDeclaration($tokens, $index);

        return !in_array(
            $previousTokenId,
            [
                T_AS,
                T_ATTRIBUTE,
                T_CLASS,
                T_CONST,
                T_ENUM,
                T_EXTENDS,
                T_FUNCTION,
                T_GOTO,
                T_IMPLEMENTS,
                T_INSTANCEOF,
                T_INSTEADOF,
                T_INTERFACE,
                T_NAMESPACE,
                T_NEW,
                T_OBJECT_OPERATOR,
                T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON,
                T_TRAIT,
                T_USE,
            ],
            true,
        )
            && !in_array($nextTokenId, [T_VARIABLE, T_DOUBLE_COLON], true)
            && !$isNamedArgumentOrLabel
            && !$isEnumCaseDeclaration
            && !self::isCompoundTypeReference($tokens, $index);
    }

    /**
     * Enum cases and switch cases share T_CASE. Only a case directly enclosed
     * by an enum declaration introduces a name instead of reading a value.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isEnumCaseDeclaration(array $tokens, int $index): bool
    {
        $caseIndex = self::previousSignificantTokenIndex($tokens, $index - 1);
        $case = $caseIndex === null ? null : $tokens[$caseIndex];

        if (!is_array($case) || $case[0] !== T_CASE) {
            return false;
        }

        $braceDepth = 0;
        $openIndex = null;

        for ($previous = $caseIndex - 1; $previous >= 0; $previous--) {
            $text = self::tokenText($tokens[$previous]);

            if ($text === '}') {
                $braceDepth++;
                continue;
            }

            if ($text !== '{') {
                continue;
            }

            if ($braceDepth > 0) {
                $braceDepth--;
                continue;
            }

            $openIndex = $previous;
            break;
        }

        if ($openIndex === null) {
            return false;
        }

        for ($previous = $openIndex - 1; $previous >= 0; $previous--) {
            $token = $tokens[$previous];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($token) && $token[0] === T_ENUM) {
                return true;
            }

            if (in_array(self::tokenText($token), [';', '{', '}'], true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isCompoundTypeReference(array $tokens, int $index): bool
    {
        $expectType = false;

        for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
            $token = $tokens[$next];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = self::tokenText($token);

            if (is_array($token) && $token[0] === T_VARIABLE) {
                if (!$expectType) {
                    return true;
                }

                break;
            }

            if (in_array($text, ['|', '&'], true)) {
                if ($expectType) {
                    break;
                }

                $expectType = true;
                continue;
            }

            if (
                $expectType
                && is_array($token)
                && in_array(
                    $token[0],
                    [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                    true,
                )
            ) {
                $expectType = false;
                continue;
            }

            if (in_array($text, ['(', ')', '?'], true)) {
                continue;
            }

            break;
        }

        $expectType = false;

        for ($previous = $index - 1; $previous >= 0; $previous--) {
            $token = $tokens[$previous];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = self::tokenText($token);

            if ($text === ':') {
                return !$expectType && self::colonStartsReturnType($tokens, $previous);
            }

            if (in_array($text, ['|', '&'], true)) {
                if ($expectType) {
                    return false;
                }

                $expectType = true;
                continue;
            }

            if (
                $expectType
                && is_array($token)
                && in_array(
                    $token[0],
                    [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                    true,
                )
            ) {
                $expectType = false;
                continue;
            }

            if (in_array($text, ['(', ')', '?'], true)) {
                continue;
            }

            return false;
        }

        return false;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function colonStartsReturnType(array $tokens, int $colonIndex): bool
    {
        $closeIndex = self::previousSignificantTokenIndex($tokens, $colonIndex - 1);

        if ($closeIndex === null || self::tokenText($tokens[$closeIndex]) !== ')') {
            return false;
        }

        $depth = 1;
        $openIndex = null;

        for ($index = $closeIndex - 1; $index >= 0; $index--) {
            $text = self::tokenText($tokens[$index]);

            if ($text === ')') {
                $depth++;
            } elseif ($text === '(') {
                $depth--;

                if ($depth === 0) {
                    $openIndex = $index;
                    break;
                }
            }
        }

        if ($openIndex === null) {
            return false;
        }

        $declarationIndex = self::previousSignificantTokenIndex($tokens, $openIndex - 1);

        if ($declarationIndex === null) {
            return false;
        }

        $declaration = $tokens[$declarationIndex];

        if (is_array($declaration) && in_array($declaration[0], [T_FUNCTION, T_FN], true)) {
            return true;
        }

        if (is_array($declaration) && $declaration[0] === T_STRING) {
            $declarationIndex = self::previousSignificantTokenIndex($tokens, $declarationIndex - 1);
            $declaration = $declarationIndex === null ? null : $tokens[$declarationIndex];
        }

        if (
            $declarationIndex !== null
            && $declaration !== null
            && self::tokenText($declaration) === '&'
        ) {
            $declarationIndex = self::previousSignificantTokenIndex($tokens, $declarationIndex - 1);
            $declaration = $declarationIndex === null ? null : $tokens[$declarationIndex];
        }

        return is_array($declaration) && in_array($declaration[0], [T_FUNCTION, T_FN], true);
    }

    private static function literalEnvironmentFunction(string $tokenText): ?string
    {
        $literal = self::decodedLiteral($tokenText);

        if ($literal === null) {
            return null;
        }

        return self::environmentFunctionName($literal);
    }

    private static function environmentFunctionName(string $name): ?string
    {
        $normalized = strtolower(ltrim($name, '\\'));
        $separator = strrpos($normalized, '\\');

        if ($separator !== false) {
            $normalized = substr($normalized, $separator + 1);
        }

        return in_array($normalized, self::ENVIRONMENT_FUNCTIONS, true) ? $normalized : null;
    }

    private static function isInputEnvConstantName(?string $name): bool
    {
        return $name === 'INPUT_ENV' || $name === '\\INPUT_ENV';
    }

    /**
     * Record namespace-segment bindings that make a bare INPUT_ENV name refer
     * to an application constant instead of PHP's global INPUT_ENV constant.
     * Fully qualified \INPUT_ENV references never use these bindings.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{
     *     segments: list<int>,
     *     safeImports: array<int, int>,
     *     declarations: array<int, array{name: int, active: int}>
     * }
     */
    private static function inputEnvBindings(array $tokens): array
    {
        $segments = [];
        $segment = 0;
        $namedNamespaceSegments = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $segment++;
                $nameIndex = self::nextSignificantTokenIndex($tokens, $index + 1);
                $name = $nameIndex === null ? null : $tokens[$nameIndex];

                if (
                    is_array($name)
                    && in_array(
                        $name[0],
                        [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                        true,
                    )
                ) {
                    $namedNamespaceSegments[$segment] = true;
                }
            }

            $segments[] = $segment;
        }

        $safeImports = [];
        $declarations = [];
        $braceContexts = [];
        $classDepth = 0;
        $pendingClassLike = false;

        foreach ($tokens as $index => $token) {
            $tokenId = is_array($token) ? $token[0] : null;
            $text = self::tokenText($token);
            $currentSegment = $segments[$index];

            if (
                is_array($token)
                && in_array($tokenId, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
            ) {
                $pendingClassLike = true;
            }

            if ($text === '{') {
                $braceContexts[] = $pendingClassLike;

                if ($pendingClassLike) {
                    $classDepth++;
                }

                $pendingClassLike = false;
                continue;
            }

            if ($text === '}') {
                $wasClass = array_pop($braceContexts);

                if ($wasClass === true) {
                    $classDepth--;
                }

                continue;
            }

            if ($text === ';') {
                $pendingClassLike = false;
                continue;
            }

            if ($tokenId === T_USE) {
                $constIndex = self::nextSignificantTokenIndex($tokens, $index + 1);

                if (
                    $constIndex !== null
                    && is_array($tokens[$constIndex])
                    && $tokens[$constIndex][0] === T_CONST
                    && self::constantImportBindsSafeInputEnv($tokens, $constIndex + 1)
                ) {
                    $safeImports[$currentSegment] = $index;
                }

                continue;
            }

            if (
                $tokenId === T_CONST
                && $classDepth === 0
                && isset($namedNamespaceSegments[$currentSegment])
            ) {
                $declaration = self::namespaceInputEnvDeclaration($tokens, $index + 1);

                if ($declaration !== null) {
                    $declarations[$currentSegment] = $declaration;
                }
            }
        }

        return [
            'segments' => $segments,
            'safeImports' => $safeImports,
            'declarations' => $declarations,
        ];
    }

    /**
     * @param array{
     *     segments: list<int>,
     *     safeImports: array<int, int>,
     *     declarations: array<int, array{name: int, active: int}>
     * } $bindings
     */
    private static function hasSafeInputEnvBinding(array $bindings, int $index): bool
    {
        $segment = $bindings['segments'][$index] ?? 0;
        $declaration = $bindings['declarations'][$segment] ?? null;

        return ($bindings['safeImports'][$segment] ?? $index) < $index
            || (
                $declaration !== null
                && (
                    $declaration['name'] === $index
                    || $declaration['active'] < $index
                )
            );
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function constantImportBindsSafeInputEnv(array $tokens, int $start): bool
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            $tokenId = is_array($token) ? $token[0] : null;
            $text = self::tokenText($token);

            if ($text === ';') {
                return false;
            }

            if ($tokenId === T_AS) {
                $aliasIndex = self::nextSignificantTokenIndex($tokens, $index + 1);
                $sourceIndex = self::previousSignificantTokenIndex($tokens, $index - 1);
                $alias = $aliasIndex === null ? null : $tokens[$aliasIndex];
                $source = $sourceIndex === null ? null : $tokens[$sourceIndex];

                if (
                    is_array($alias)
                    && $alias[0] === T_STRING
                    && $alias[1] === 'INPUT_ENV'
                    && is_array($source)
                    && in_array(
                        $source[0],
                        [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                        true,
                    )
                    && (
                        !self::isInputEnvConstantName($source[1])
                        || self::isGroupedImportMember($tokens, $sourceIndex)
                    )
                ) {
                    return true;
                }

                continue;
            }

            if (
                !is_array($token)
                || !in_array(
                    $tokenId,
                    [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                    true,
                )
                || self::nextSignificantTokenText($tokens, $index + 1) === 'as'
            ) {
                continue;
            }

            $normalized = ltrim($text, '\\');
            $separator = strrpos($normalized, '\\');
            $localName = $separator === false
                ? $normalized
                : substr($normalized, $separator + 1);

            if (
                $localName === 'INPUT_ENV'
                && (
                    $separator !== false
                    || self::isGroupedImportMember($tokens, $index)
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{name: int, active: int}|null
     */
    private static function namespaceInputEnvDeclaration(array $tokens, int $start): ?array
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            $text = self::tokenText($token);

            if ($text === ';') {
                return null;
            }

            if (
                is_array($token)
                && $token[0] === T_STRING
                && $token[1] === 'INPUT_ENV'
                && self::nextSignificantTokenText($tokens, $index + 1) === '='
            ) {
                $activeIndex = self::constantDeclaratorEnd($tokens, $index + 1);

                return $activeIndex === null
                    ? null
                    : ['name' => $index, 'active' => $activeIndex];
            }
        }

        return null;
    }

    /**
     * The new constant name is not visible inside its own initializer. It
     * becomes available only after that declarator ends at a top-level comma
     * or semicolon.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function constantDeclaratorEnd(array $tokens, int $start): ?int
    {
        $parenthesisDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;

        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $text = self::tokenText($tokens[$index]);

            if ($text === '(') {
                $parenthesisDepth++;
                continue;
            }

            if ($text === ')') {
                $parenthesisDepth--;
                continue;
            }

            if ($text === '[') {
                $bracketDepth++;
                continue;
            }

            if ($text === ']') {
                $bracketDepth--;
                continue;
            }

            if ($text === '{') {
                $braceDepth++;
                continue;
            }

            if ($text === '}') {
                $braceDepth--;
                continue;
            }

            if (
                in_array($text, [',', ';'], true)
                && $parenthesisDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
            ) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array<string, list<int>>
     */
    private static function variableOccurrences(array $tokens): array
    {
        $occurrences = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $occurrences[$token[1]][] = $index;
            }
        }

        return $occurrences;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isImportAlias(array $tokens, int $index): bool
    {
        $previousIndex = self::previousSignificantTokenIndex($tokens, $index - 1);
        $previous = $previousIndex === null ? null : $tokens[$previousIndex];

        return is_array($previous) && $previous[0] === T_AS;
    }

    /**
     * A grouped import member inherits the non-empty namespace prefix before
     * the opening brace, so a member spelled INPUT_ENV is not the global
     * INPUT_ENV constant.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isGroupedImportMember(array $tokens, int $index): bool
    {
        for ($previous = $index - 1; $previous >= 0; $previous--) {
            $token = $tokens[$previous];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = self::tokenText($token);

            if ($text === '{') {
                return true;
            }

            if ($text === ';' || (is_array($token) && $token[0] === T_CONST)) {
                return false;
            }
        }

        return false;
    }

    private static function decodedLiteral(string $tokenText): ?string
    {
        if (strlen($tokenText) < 2) {
            return null;
        }

        $quote = $tokenText[0];

        if (!in_array($quote, ["'", '"'], true) || $tokenText[-1] !== $quote) {
            return null;
        }

        $body = substr($tokenText, 1, -1);

        if ($quote === '"') {
            return self::decodedDoubleQuotedLiteral($body);
        }

        $decoded = '';

        for ($index = 0, $length = strlen($body); $index < $length; $index++) {
            if (
                $body[$index] === '\\'
                && $index + 1 < $length
                && in_array($body[$index + 1], ['\\', "'"], true)
            ) {
                $index++;
            }

            $decoded .= $body[$index];
        }

        return $decoded;
    }

    private static function decodedDoubleQuotedLiteral(string $body): string
    {
        $decoded = '';

        for ($index = 0, $length = strlen($body); $index < $length; $index++) {
            if ($body[$index] !== '\\' || $index + 1 >= $length) {
                $decoded .= $body[$index];
                continue;
            }

            $escape = $body[++$index];
            $simpleEscape = match ($escape) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'v' => "\v",
                'e' => "\e",
                'f' => "\f",
                '\\', '$', '"' => $escape,
                default => null,
            };

            if ($simpleEscape !== null) {
                $decoded .= $simpleEscape;
                continue;
            }

            if ($escape >= '0' && $escape <= '7') {
                $octal = $escape;

                while (
                    strlen($octal) < 3
                    && $index + 1 < $length
                    && $body[$index + 1] >= '0'
                    && $body[$index + 1] <= '7'
                ) {
                    $octal .= $body[++$index];
                }

                $decoded .= chr(intval($octal, 8) & 0xff);
                continue;
            }

            if ($escape === 'x') {
                $hexadecimal = '';

                while (
                    strlen($hexadecimal) < 2
                    && $index + 1 < $length
                    && ctype_xdigit($body[$index + 1])
                ) {
                    $hexadecimal .= $body[++$index];
                }

                $decoded .= $hexadecimal === ''
                    ? '\\x'
                    : chr(intval($hexadecimal, 16));
                continue;
            }

            if ($escape === 'u' && $index + 1 < $length && $body[$index + 1] === '{') {
                $close = strpos($body, '}', $index + 2);
                $hexadecimal = $close === false
                    ? ''
                    : substr($body, $index + 2, $close - $index - 2);

                if (
                    $close !== false
                    && $hexadecimal !== ''
                    && ctype_xdigit($hexadecimal)
                ) {
                    $decoded .= self::utf8CodePoint(intval($hexadecimal, 16));
                    $index = $close;
                    continue;
                }
            }

            $decoded .= '\\' . $escape;
        }

        return $decoded;
    }

    private static function utf8CodePoint(int $codePoint): string
    {
        if ($codePoint < 0 || $codePoint > 0x10ffff) {
            return '';
        }

        if ($codePoint <= 0x7f) {
            return chr($codePoint);
        }

        if ($codePoint <= 0x7ff) {
            return chr(0xc0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3f));
        }

        if ($codePoint <= 0xffff) {
            return chr(0xe0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3f))
                . chr(0x80 | ($codePoint & 0x3f));
        }

        return chr(0xf0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3f))
            . chr(0x80 | (($codePoint >> 6) & 0x3f))
            . chr(0x80 | ($codePoint & 0x3f));
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param list<array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}> $callableScopes
     * @param array<string, list<int>> $variableOccurrences
     */
    private static function isLiteralCallableReference(
        array $tokens,
        array $callableScopes,
        array $variableOccurrences,
        int $index,
    ): bool {
        if (self::nextSignificantTokenText($tokens, $index + 1) === '(') {
            return true;
        }

        $bounds = self::literalExpressionBounds($tokens, $index);

        if (self::nextSignificantTokenText($tokens, $bounds['right'] + 1) === '(') {
            $beforeIndex = self::previousSignificantTokenIndex(
                $tokens,
                $bounds['left'] - 1,
            );
            $before = $beforeIndex === null ? null : $tokens[$beforeIndex];

            if (!is_array($before) || $before[0] !== T_NEW) {
                return true;
            }
        }

        $call = self::directCallContext($tokens, $bounds['left']);

        if (
            $call !== null
            && self::callAcceptsEnvironmentLiteral($call)
        ) {
            return true;
        }

        $previousIndex = self::previousSignificantTokenIndex($tokens, $bounds['left'] - 1);

        if ($previousIndex === null || self::tokenText($tokens[$previousIndex]) !== '=') {
            return false;
        }

        $variableIndex = self::previousSignificantTokenIndex($tokens, $previousIndex - 1);
        $variable = $variableIndex === null ? null : $tokens[$variableIndex];

        if (!is_array($variable) || $variable[0] !== T_VARIABLE) {
            return false;
        }

        $assignmentScope = self::callableScopeAt($callableScopes, $index);
        $searchEnd = $assignmentScope['end'] ?? count($tokens);

        foreach ($variableOccurrences[$variable[1]] ?? [] as $callIndex) {
            if ($callIndex <= $index) {
                continue;
            }

            if ($callIndex >= $searchEnd) {
                break;
            }

            if (
                !self::scopeCanAccessAssignedVariable(
                    $tokens,
                    $callableScopes,
                    $assignmentScope,
                    self::callableScopeAt($callableScopes, $callIndex),
                    $variable[1],
                )
            ) {
                continue;
            }

            $candidateBounds = self::literalExpressionBounds($tokens, $callIndex);
            $nextToken = self::nextSignificantTokenText(
                $tokens,
                $candidateBounds['right'] + 1,
            );

            if ($nextToken === '(') {
                $beforeIndex = self::previousSignificantTokenIndex(
                    $tokens,
                    $candidateBounds['left'] - 1,
                );
                $before = $beforeIndex === null ? null : $tokens[$beforeIndex];
                $beforeId = is_array($before) ? $before[0] : null;

                if (
                    !in_array(
                        $beforeId,
                        [
                            T_NEW,
                            T_OBJECT_OPERATOR,
                            T_NULLSAFE_OBJECT_OPERATOR,
                            T_DOUBLE_COLON,
                        ],
                        true,
                    )
                ) {
                    return true;
                }
            }

            if (
                in_array(
                    self::nextSignificantTokenText($tokens, $callIndex + 1),
                    ['=', '+=', '-=', '*=', '/=', '.=', '%=', '&=', '|=', '^=', '<<=', '>>=', '??='],
                    true,
                )
            ) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array{name: string, argument: int, parameter: ?string, kind: 'function'|'method'|'static', native: bool} $call
     */
    private static function callAcceptsEnvironmentLiteral(array $call): bool
    {
        return $call['native'] && self::callMatchesCallableMetadata(
            $call,
            self::CALLABLE_ARGUMENT_POSITIONS[$call['name']] ?? [],
            self::CALLABLE_ARGUMENT_NAMES[$call['name']] ?? [],
        );
    }

    /**
     * @param array{name: string, argument: int, parameter: ?string, kind: 'function'|'method'|'static', native: bool} $call
     * @param list<int> $positions
     * @param list<string> $parameters
     */
    private static function callMatchesCallableMetadata(
        array $call,
        array $positions,
        array $parameters,
    ): bool {
        if ($call['parameter'] !== null) {
            return in_array($call['parameter'], $parameters, true);
        }

        return in_array($call['argument'], $positions, true);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isConstantLookupArgument(array $tokens, int $index): bool
    {
        $bounds = self::literalExpressionBounds($tokens, $index);
        $call = self::directCallContext($tokens, $bounds['left']);

        return $call !== null
            && $call['name'] === 'constant'
            && $call['kind'] === 'function'
            && $call['native']
            && ($call['argument'] === 1 || $call['parameter'] === 'name');
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isCanonicalServerTransportHandoff(
        array $tokens,
        int $index,
        string $relativePath,
    ): bool {
        if ($relativePath !== 'public/index.php') {
            return false;
        }

        $previousIndex = self::previousSignificantTokenIndex($tokens, $index - 1);

        if ($previousIndex === null || self::tokenText($tokens[$previousIndex]) !== '(') {
            return false;
        }

        $methodIndex = self::previousSignificantTokenIndex($tokens, $previousIndex - 1);
        $method = $methodIndex === null ? null : $tokens[$methodIndex];
        $operatorIndex = $methodIndex === null
            ? null
            : self::previousSignificantTokenIndex($tokens, $methodIndex - 1);
        $operator = $operatorIndex === null ? null : $tokens[$operatorIndex];
        $receiverIndex = $operatorIndex === null
            ? null
            : self::previousSignificantTokenIndex($tokens, $operatorIndex - 1);
        $receiver = $receiverIndex === null ? null : $tokens[$receiverIndex];

        if (
            !is_array($method)
            || $method[0] !== T_STRING
            || $method[1] !== 'handle'
            || !is_array($operator)
            || $operator[0] !== T_OBJECT_OPERATOR
            || !is_array($receiver)
            || $receiver[0] !== T_VARIABLE
            || $receiver[1] !== '$coordinator'
        ) {
            return false;
        }

        $expected = [
            ',',
            '$_GET',
            ',',
            '$_POST',
            ',',
            '$_FILES',
            ')',
        ];
        $nextIndex = $index + 1;

        foreach ($expected as $expectedToken) {
            $nextIndex = self::nextSignificantTokenIndex($tokens, $nextIndex);

            if (
                $nextIndex === null
                || self::tokenText($tokens[$nextIndex]) !== $expectedToken
            ) {
                return false;
            }

            $nextIndex++;
        }

        return true;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{left: int, right: int}
     */
    private static function literalExpressionBounds(array $tokens, int $index): array
    {
        $left = $index;
        $right = $index;

        while (true) {
            $openIndex = self::previousSignificantTokenIndex($tokens, $left - 1);
            $closeIndex = self::nextSignificantTokenIndex($tokens, $right + 1);

            if (
                $openIndex === null
                || $closeIndex === null
                || self::tokenText($tokens[$openIndex]) !== '('
                || self::tokenText($tokens[$closeIndex]) !== ')'
                || self::openingParenthesisStartsCall($tokens, $openIndex)
            ) {
                break;
            }

            $left = $openIndex;
            $right = $closeIndex;
        }

        return ['left' => $left, 'right' => $right];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{name: string, argument: int, parameter: ?string, kind: 'function'|'method'|'static', native: bool}|null
     */
    private static function directCallContext(array $tokens, int $expressionStart): ?array
    {
        $parenthesisDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $argument = 1;
        $parameter = null;
        $separatorIndex = self::previousSignificantTokenIndex($tokens, $expressionStart - 1);

        if (
            $separatorIndex !== null
            && self::tokenText($tokens[$separatorIndex]) === ':'
        ) {
            $parameterIndex = self::previousSignificantTokenIndex($tokens, $separatorIndex - 1);
            $parameterToken = $parameterIndex === null ? null : $tokens[$parameterIndex];

            if (is_array($parameterToken) && $parameterToken[0] === T_STRING) {
                $parameter = strtolower($parameterToken[1]);
            }
        }

        for ($index = $expressionStart - 1; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = self::tokenText($token);

            if ($text === ')') {
                $parenthesisDepth++;
                continue;
            }

            if ($text === '(') {
                if ($parenthesisDepth > 0) {
                    $parenthesisDepth--;
                    continue;
                }

                if ($bracketDepth !== 0 || $braceDepth !== 0) {
                    return null;
                }

                $functionIndex = self::previousSignificantTokenIndex($tokens, $index - 1);
                $function = $functionIndex === null ? null : $tokens[$functionIndex];

                if (
                    !is_array($function)
                    || !in_array(
                        $function[0],
                        [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
                        true,
                    )
                ) {
                    return null;
                }

                $previousIndex = self::previousSignificantTokenIndex($tokens, $functionIndex - 1);
                $previous = $previousIndex === null ? null : $tokens[$previousIndex];
                $previousId = is_array($previous) ? $previous[0] : null;
                $name = strtolower(ltrim($function[1], '\\'));
                $separator = strrpos($name, '\\');

                if (in_array($previousId, [T_ATTRIBUTE, T_FUNCTION, T_NEW], true)) {
                    return null;
                }

                if ($separator !== false) {
                    $name = substr($name, $separator + 1);
                }

                $kind = match ($previousId) {
                    T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR => 'method',
                    T_DOUBLE_COLON => 'static',
                    default => 'function',
                };
                $native = $kind === 'function'
                    && (
                        $function[0] === T_STRING
                        || (
                            $function[0] === T_NAME_FULLY_QUALIFIED
                            && strtolower($function[1]) === "\\{$name}"
                        )
                    );

                if ($kind === 'static' && $name === 'fromcallable') {
                    $receiverIndex = self::previousSignificantTokenIndex(
                        $tokens,
                        $previousIndex - 1,
                    );
                    $receiver = $receiverIndex === null ? null : $tokens[$receiverIndex];
                    $native = is_array($receiver)
                        && (
                            (
                                $receiver[0] === T_NAME_FULLY_QUALIFIED
                                && strtolower($receiver[1]) === '\\closure'
                            )
                            || (
                                $receiver[0] === T_STRING
                                && strtolower($receiver[1]) === 'closure'
                            )
                        );
                }

                return [
                    'name' => $name,
                    'argument' => $argument,
                    'parameter' => $parameter,
                    'kind' => $kind,
                    'native' => $native,
                ];
            }

            if ($text === ']') {
                $bracketDepth++;
                continue;
            }

            if ($text === '[') {
                if ($bracketDepth === 0) {
                    return null;
                }

                $bracketDepth--;
                continue;
            }

            if ($text === '}') {
                $braceDepth++;
                continue;
            }

            if ($text === '{') {
                if ($braceDepth === 0) {
                    return null;
                }

                $braceDepth--;
                continue;
            }

            if (
                $text === ','
                && $parenthesisDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
            ) {
                $argument++;
                continue;
            }

            if (
                $text === ';'
                && $parenthesisDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
            ) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function openingParenthesisStartsCall(array $tokens, int $openIndex): bool
    {
        $previousIndex = self::previousSignificantTokenIndex($tokens, $openIndex - 1);
        $previous = $previousIndex === null ? null : $tokens[$previousIndex];

        if (!is_array($previous)) {
            return in_array($previous, [')', ']'], true);
        }

        return in_array(
            $previous[0],
            [
                T_NAME_FULLY_QUALIFIED,
                T_NAME_QUALIFIED,
                T_NAME_RELATIVE,
                T_STRING,
                T_VARIABLE,
            ],
            true,
        );
    }

    /**
     * Return the innermost function or arrow-function token range containing the
     * target. PHP variables are function-scoped, not block-scoped.
     *
     * @param list<array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}> $callableScopes
     * @return array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}|null
     */
    private static function callableScopeAt(array $callableScopes, int $target): ?array
    {
        $scopes = self::callableScopesAt($callableScopes, $target);

        return $scopes === [] ? null : $scopes[array_key_last($scopes)];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return list<array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}>
     */
    private static function callableScopes(array $tokens): array
    {
        $scopes = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_FUNCTION, T_FN], true)) {
                continue;
            }

            $kind = $token[0] === T_FUNCTION ? 'function' : 'arrow';
            $scopeEnd = $kind === 'function'
                ? self::functionScopeEnd($tokens, $index)
                : self::arrowFunctionScopeEnd($tokens, $index);

            if ($scopeEnd === null) {
                continue;
            }

            $scopes[] = [
                'start' => $index,
                'end' => $scopeEnd,
                'kind' => $kind,
                'anonymous' => $kind === 'arrow' || self::functionIsAnonymous($tokens, $index),
            ];
        }

        return $scopes;
    }

    /**
     * @param list<array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}> $callableScopes
     * @return list<array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}>
     */
    private static function callableScopesAt(array $callableScopes, int $target): array
    {
        $scopes = [];

        foreach ($callableScopes as $scope) {
            if ($scope['start'] < $target && $target < $scope['end']) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param list<array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}> $callableScopes
     * @param array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}|null $assignmentScope
     * @param array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool}|null $candidateScope
     */
    private static function scopeCanAccessAssignedVariable(
        array $tokens,
        array $callableScopes,
        ?array $assignmentScope,
        ?array $candidateScope,
        string $variable,
    ): bool {
        if ($candidateScope === $assignmentScope) {
            return true;
        }

        $candidateScopes = $candidateScope === null
            ? []
            : self::callableScopesAt($callableScopes, $candidateScope['start'] + 1);
        $assignmentScopes = $assignmentScope === null
            ? []
            : self::callableScopesAt($callableScopes, $assignmentScope['start'] + 1);

        if (count($candidateScopes) <= count($assignmentScopes)) {
            return false;
        }

        foreach ($assignmentScopes as $index => $scope) {
            if (($candidateScopes[$index] ?? null) !== $scope) {
                return false;
            }
        }

        foreach (array_slice($candidateScopes, count($assignmentScopes)) as $scope) {
            if (!self::scopeCapturesVariable($tokens, $scope, $variable)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool} $scope
     */
    private static function scopeCapturesVariable(
        array $tokens,
        array $scope,
        string $variable,
    ): bool {
        if (self::scopeParameterNames($tokens, $scope) !== []) {
            foreach (self::scopeParameterNames($tokens, $scope) as $parameter) {
                if ($parameter === $variable) {
                    return false;
                }
            }
        }

        if ($scope['kind'] === 'arrow') {
            return true;
        }

        if (!$scope['anonymous']) {
            return false;
        }

        $insideUse = false;
        $useDepth = 0;

        for ($index = $scope['start'] + 1; $index < $scope['end']; $index++) {
            $token = $tokens[$index];
            $text = self::tokenText($token);

            if (!$insideUse && $text === '{') {
                return false;
            }

            if (is_array($token) && $token[0] === T_USE) {
                $insideUse = true;
                continue;
            }

            if (!$insideUse) {
                continue;
            }

            if ($text === '(') {
                $useDepth++;
                continue;
            }

            if ($text === ')') {
                $useDepth--;

                if ($useDepth === 0) {
                    return false;
                }

                continue;
            }

            if (
                $useDepth === 1
                && is_array($token)
                && $token[0] === T_VARIABLE
                && $token[1] === $variable
            ) {
                return true;
            }

            if ($text === '{') {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array{start: int, end: int, kind: 'function'|'arrow', anonymous: bool} $scope
     * @return list<string>
     */
    private static function scopeParameterNames(array $tokens, array $scope): array
    {
        $openIndex = self::nextSignificantTokenIndex($tokens, $scope['start'] + 1);

        if ($openIndex !== null && self::tokenText($tokens[$openIndex]) === '&') {
            $openIndex = self::nextSignificantTokenIndex($tokens, $openIndex + 1);
        }

        if ($openIndex === null || self::tokenText($tokens[$openIndex]) !== '(') {
            return [];
        }

        $names = [];
        $depth = 1;

        for ($index = $openIndex + 1; $index < $scope['end']; $index++) {
            $token = $tokens[$index];
            $text = self::tokenText($token);

            if ($text === '(') {
                $depth++;
            } elseif ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            } elseif ($depth === 1 && is_array($token) && $token[0] === T_VARIABLE) {
                $names[] = $token[1];
            }
        }

        return $names;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function functionIsAnonymous(array $tokens, int $functionIndex): bool
    {
        $nextIndex = self::nextSignificantTokenIndex($tokens, $functionIndex + 1);

        if ($nextIndex !== null && self::tokenText($tokens[$nextIndex]) === '&') {
            $nextIndex = self::nextSignificantTokenIndex($tokens, $nextIndex + 1);
        }

        return $nextIndex !== null && self::tokenText($tokens[$nextIndex]) === '(';
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function functionScopeEnd(array $tokens, int $functionIndex): ?int
    {
        $parenthesisDepth = 0;
        $bracketDepth = 0;

        for ($index = $functionIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $text = self::tokenText($tokens[$index]);

            if ($text === '(') {
                $parenthesisDepth++;
            } elseif ($text === ')') {
                $parenthesisDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($text === ';' && $parenthesisDepth === 0 && $bracketDepth === 0) {
                return null;
            } elseif ($text === '{' && $parenthesisDepth === 0 && $bracketDepth === 0) {
                return self::matchingBrace($tokens, $index);
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function arrowFunctionScopeEnd(array $tokens, int $functionIndex): ?int
    {
        $arrowIndex = self::arrowFunctionSeparatorIndex($tokens, $functionIndex);

        if ($arrowIndex === null) {
            return null;
        }

        $parenthesisDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;

        for ($index = $arrowIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $text = self::tokenText($tokens[$index]);

            if ($text === '(') {
                $parenthesisDepth++;
                continue;
            }

            if ($text === '[') {
                $bracketDepth++;
                continue;
            }

            if (
                $text === '{'
                || (
                    is_array($tokens[$index])
                    && in_array(
                        $tokens[$index][0],
                        [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES],
                        true,
                    )
                )
            ) {
                $braceDepth++;
                continue;
            }

            if ($text === ')') {
                if ($parenthesisDepth === 0) {
                    return $index;
                }

                $parenthesisDepth--;
                continue;
            }

            if ($text === ']') {
                if ($bracketDepth === 0) {
                    return $index;
                }

                $bracketDepth--;
                continue;
            }

            if ($text === '}') {
                if ($braceDepth === 0) {
                    return $index;
                }

                $braceDepth--;
                continue;
            }

            if (
                in_array($text, [',', ';'], true)
                && $parenthesisDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
            ) {
                return $index;
            }
        }

        return count($tokens);
    }

    /**
     * Find the arrow that separates the parameter/return-type declaration from
     * the body. T_DOUBLE_ARROW tokens inside keyed-array parameter defaults do
     * not delimit the arrow function.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function arrowFunctionSeparatorIndex(array $tokens, int $functionIndex): ?int
    {
        $openIndex = self::nextSignificantTokenIndex($tokens, $functionIndex + 1);

        if ($openIndex !== null && self::tokenText($tokens[$openIndex]) === '&') {
            $openIndex = self::nextSignificantTokenIndex($tokens, $openIndex + 1);
        }

        if ($openIndex === null || self::tokenText($tokens[$openIndex]) !== '(') {
            return null;
        }

        $parenthesisDepth = 1;
        $closeIndex = null;

        for ($index = $openIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $text = self::tokenText($tokens[$index]);

            if ($text === '(') {
                $parenthesisDepth++;
                continue;
            }

            if ($text !== ')') {
                continue;
            }

            $parenthesisDepth--;

            if ($parenthesisDepth === 0) {
                $closeIndex = $index;
                break;
            }
        }

        if ($closeIndex === null) {
            return null;
        }

        for ($index = $closeIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
                return $index;
            }

            if (
                !is_array($token)
                || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $text = self::tokenText($token);

                if (in_array($text, [';', '{', '}'], true)) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function matchingBrace(array $tokens, int $openIndex): ?int
    {
        $depth = 1;

        for ($index = $openIndex + 1, $count = count($tokens); $index < $count; $index++) {
            if (
                (!is_array($tokens[$index]) && $tokens[$index] === '{')
                || (
                    is_array($tokens[$index])
                    && in_array(
                        $tokens[$index][0],
                        [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES],
                        true,
                    )
                )
            ) {
                $depth++;
            } elseif (!is_array($tokens[$index]) && $tokens[$index] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function nextSignificantTokenText(array $tokens, int $start): ?string
    {
        $index = self::nextSignificantTokenIndex($tokens, $start);

        return $index === null ? null : self::tokenText($tokens[$index]);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function nextSignificantTokenIndex(array $tokens, int $start): ?int
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function previousSignificantTokenIndex(array $tokens, int $start): ?int
    {
        for ($index = $start; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /** @param array{int, string, int}|string $token */
    private static function tokenText(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }
}
