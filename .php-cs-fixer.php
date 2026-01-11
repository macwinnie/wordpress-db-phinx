<?php


$require_files = [
    '/vendor/autoload.php',
];

foreach ($require_files as $file) {
    require_once __DIR__ . $file;
}

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new Config();
$config
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'braces_position' => [
            'classes_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line',
            'anonymous_classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
        ],
        'class_attributes_separation' => [
            'elements' => ['const' => 'only_if_meta', 'property' => 'only_if_meta', 'method' => 'one'],
        ],
        'function_declaration' => [
            'closure_fn_spacing' => 'one',
            'closure_function_spacing' => 'one',
            'trailing_comma_single_line' => true,
        ],
        'control_structure_braces' => true,
        'statement_indentation' => true,
        'no_extra_blank_lines' => true,
        'single_space_around_construct' => true,
        'no_multiple_statements_per_line' => true,
        'switch_case_space' => true,
        'trailing_comma_in_multiline' => true,
        'no_superfluous_elseif' => false,
        'no_useless_else' => true,
        'blank_line_between_import_groups' => true,
        'compact_nullable_type_declaration' => true,
        'array_indentation' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'case', 'continue', 'declare', 'default', 'do', 'exit', 'for', 'foreach', 'goto', 'if', 'include', 'include_once', 'phpdoc', 'require', 'require_once', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from'],
        ],
        'no_extra_blank_lines' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
    ])
    ->setFinder($finder);

return $config;
