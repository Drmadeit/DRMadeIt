<?php
declare(strict_types=1);

/** Shop calculators. Each has a view in views/calc/<slug>.php; the math runs in the browser. */
const CALCS = [
    'fractions' => ['title' => 'Fraction Math & Converter', 'blurb' => 'Add and subtract feet, inches and fractions. Convert to decimal and mm.'],
    'square' => ['title' => 'Square Corner (3-4-5)', 'blurb' => 'Check a corner or a box for square with the diagonal.'],
    'crown' => ['title' => 'Crown Molding Angles', 'blurb' => 'Miter and bevel settings for any wall corner and spring angle.'],
    'spacing' => ['title' => 'Equal Spacing', 'blurb' => 'Evenly space shelves, dividers, balusters or holes. Gives every mark.'],
    'board-feet' => ['title' => 'Board Feet', 'blurb' => 'Board feet and cost for rough or dimensional lumber.'],
    'stairs' => ['title' => 'Stair Layout', 'blurb' => 'Risers, treads and stringer length from total rise.'],
    'dxf' => ['title' => 'DXF Polyline Fixer', 'blurb' => 'Join broken lines into closed shapes, remove duplicates and close small gaps for CNC.'],
];

function calc_precision(): string
{
    return '<label class="calc-field calc-prec">Round to <select name="precision">'
        . '<option value="16">1/16"</option><option value="32">1/32"</option><option value="64">1/64"</option><option value="8">1/8"</option>'
        . '</select></label>';
}

function calc_head(string $slug): string
{
    return '<a class="crumb" href="/calc">← All calculators</a><h1 class="page-title">' . e(CALCS[$slug]['title']) . '</h1>'
        . '<p class="page-lede">' . e(CALCS[$slug]['blurb']) . '</p>';
}

function calc_script(): string
{
    return '<script src="' . e(asset('js/calc.js')) . '" defer></script>';
}

const CALC_INPUT_HINT = 'Type 23 5/8, 23-5/8, 23.625, 5\' 3", or 600mm';
