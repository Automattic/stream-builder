<?php declare(strict_types=1);
/**
 * The StreamBuilder framework.
 * Copyright 2023 Automattic, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

$header = <<<'EOF'
The StreamBuilder framework.
Copyright 2023 Automattic, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
EOF;

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => $header,
            'separate' => 'bottom',
            'location' => 'after_declare_strict',
        ],
        '@PER-CS1.0' => true,
        'class_definition' => [
            'inline_constructor_arguments' => false,
            'space_before_parenthesis' => false,
        ],
        'array_indentation' => true,
        'native_function_invocation' => false, /*[
            'include' => ['@internal'],
            'scope' => 'namespaced',
        ],*/
        'global_namespace_import' => false, /*[
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],*/
        'declare_equal_normalize' => ['space' => 'none'],
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => false,
        'blank_line_between_import_groups' => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude('extras/')
            ->append([__FILE__])
    )
;

return $config;
