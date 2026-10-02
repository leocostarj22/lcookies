<?php

/**
 * Coding standard of LCookies: the rules of the Joomla core (joomla-cms .php-cs-fixer.dist.php).
 *
 * composer cs      check
 * composer cs-fix  fix
 */

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/build'])
    // Templates and layouts mix PHP and HTML, which the fixer cannot handle (as in the core).
    ->notPath('/tmpl/')
    ->notPath('/layouts/');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setRules([
        '@PSR12'                                           => true,
        'array_syntax'                                     => ['syntax' => 'short'],
        'no_trailing_comma_in_singleline'                  => true,
        'trailing_comma_in_multiline'                      => ['elements' => ['arrays']],
        'binary_operator_spaces'                           => ['operators' => ['=>' => 'align_single_space_minimal', '=' => 'align', '??=' => 'align']],
        'no_break_comment'                                 => ['comment_text' => 'No break'],
        'no_unused_imports'                                => true,
        'global_namespace_import'                          => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'ordered_imports'                                  => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'no_useless_else'                                  => true,
        'native_function_invocation'                       => ['include' => ['@compiler_optimized']],
        'nullable_type_declaration_for_default_null_value' => true,
        'no_unneeded_control_parentheses'                  => true,
        'combine_consecutive_issets'                       => true,
        'combine_consecutive_unsets'                       => true,
        'no_useless_sprintf'                               => true,
    ])
    ->setFinder($finder);
