<?php

declare(strict_types=1);

use App\Domain\DeviceControl\Models\ControlWidgetTemplate;
use App\Domain\DeviceSchema\Enums\ControlWidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it registers all control widget templates via artisan command', function (): void {
    $this->artisan('device-control:sync-widget-templates')
        ->assertSuccessful();

    expect(ControlWidgetTemplate::query()->count())->toBe(count(ControlWidgetType::cases()));

    foreach (ControlWidgetType::cases() as $widgetType) {
        expect(ControlWidgetTemplate::query()->where('key', $widgetType->value)->exists())
            ->toBeTrue();
    }
});

test('it updates existing templates when command runs again', function (): void {
    $template = ControlWidgetTemplate::query()->create([
        'key' => ControlWidgetType::Slider->value,
        'widget_type' => ControlWidgetType::Slider->value,
        'label' => 'Old Label',
        'description' => 'old',
        'input_component' => 'old',
        'supports_realtime' => false,
        'schema' => ['step' => 5],
        'is_active' => true,
    ]);

    $this->artisan('device-control:sync-widget-templates')
        ->assertSuccessful();

    $template->refresh();

    expect($template->label)
        ->toBe(ControlWidgetType::Slider->label())
        ->and($template->input_component)->toBe('range')
        ->and($template->supports_realtime)->toBeTrue();
});

test('it prunes stale templates when prune option is passed', function (): void {
    ControlWidgetTemplate::query()->create([
        'key' => 'legacy-widget',
        'widget_type' => 'legacy-widget',
        'label' => 'Legacy Widget',
        'description' => 'legacy',
        'input_component' => 'text',
        'supports_realtime' => false,
        'schema' => [],
        'is_active' => true,
    ]);

    $this->artisan('device-control:sync-widget-templates --prune')
        ->assertSuccessful();

    expect(ControlWidgetTemplate::query()->where('key', 'legacy-widget')->exists())
        ->toBeFalse();
});
