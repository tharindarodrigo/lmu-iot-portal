<?php

declare(strict_types=1);

use App\Domain\Automation\Services\GuidedConditionService;
use Tests\TestCase;

uses(TestCase::class);

it('compiles guided between and outside-between operators into json logic', function (): void {
    $service = app(GuidedConditionService::class);

    expect($service->compile([
        'left' => 'trigger.value',
        'operator' => 'between',
        'right' => 2,
        'right_secondary' => 8,
    ]))->toBe([
        'and' => [
            ['>=' => [['var' => 'trigger.value'], 2.0]],
            ['<=' => [['var' => 'trigger.value'], 8.0]],
        ],
    ])->and($service->compile([
        'left' => 'trigger.value',
        'operator' => 'outside_between',
        'right' => 2,
        'right_secondary' => 8,
    ]))->toBe([
        'or' => [
            ['<' => [['var' => 'trigger.value'], 2.0]],
            ['>' => [['var' => 'trigger.value'], 8.0]],
        ],
    ]);
});

it('derives a guided breach condition from legacy min max bounds', function (): void {
    $service = app(GuidedConditionService::class);

    expect($service->fromLegacyBounds(2.0, 8.0))->toBe([
        'condition_mode' => 'guided',
        'guided_condition' => [
            'left' => 'trigger.value',
            'operator' => 'outside_between',
            'right' => 2.0,
            'right_secondary' => 8.0,
        ],
        'condition_json_logic' => [
            'or' => [
                ['<' => [['var' => 'trigger.value'], 2.0]],
                ['>' => [['var' => 'trigger.value'], 8.0]],
            ],
        ],
    ])->and($service->label([
        'left' => 'trigger.value',
        'operator' => 'outside_between',
        'right' => 2,
        'right_secondary' => 8,
    ], 'celsius'))->toBe('Outside 2°C and 8°C');
});
