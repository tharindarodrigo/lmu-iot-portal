<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Services;

use App\Domain\DeviceControl\Models\ControlWidgetTemplate;
use App\Domain\DeviceSchema\Enums\ControlWidgetType;

class ControlWidgetTemplateRegistry
{
    /**
     * @return array{created: int, updated: int, pruned: int, total: int}
     */
    public function sync(bool $prune = false): array
    {
        $created = 0;
        $updated = 0;
        $registeredTemplateKeys = [];

        foreach ($this->defaultTemplates() as $template) {
            $registeredTemplateKeys[] = $template['key'];

            $record = ControlWidgetTemplate::query()->firstOrNew([
                'key' => $template['key'],
            ]);

            $wasRecentlyCreated = ! $record->exists;

            $record->fill($template);
            $dirty = $record->isDirty();

            if ($wasRecentlyCreated || $dirty) {
                $record->save();
            }

            if ($wasRecentlyCreated) {
                $created++;
            } elseif ($dirty) {
                $updated++;
            }
        }

        $pruned = 0;

        if ($prune) {
            $pruned = ControlWidgetTemplate::query()
                ->whereNotIn('key', $registeredTemplateKeys)
                ->delete();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'pruned' => $pruned,
            'total' => count($registeredTemplateKeys),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     widget_type: string,
     *     label: string,
     *     description: string,
     *     input_component: string,
     *     supports_realtime: bool,
     *     schema: array<string, mixed>,
     *     is_active: bool
     * }>
     */
    private function defaultTemplates(): array
    {
        return [
            $this->template(
                type: ControlWidgetType::Slider,
                description: 'Continuous numeric range control.',
                inputComponent: 'range',
                schema: ['min' => 0, 'max' => 100, 'step' => 1]
            ),
            $this->template(
                type: ControlWidgetType::Toggle,
                description: 'Boolean on/off switch.',
                inputComponent: 'checkbox',
                schema: []
            ),
            $this->template(
                type: ControlWidgetType::Button,
                description: 'Momentary trigger action.',
                inputComponent: 'button',
                schema: ['button_value' => true]
            ),
            $this->template(
                type: ControlWidgetType::Select,
                description: 'Discrete option selector.',
                inputComponent: 'select',
                schema: ['options' => []]
            ),
            $this->template(
                type: ControlWidgetType::Number,
                description: 'Numeric input field.',
                inputComponent: 'number',
                schema: ['step' => 1]
            ),
            $this->template(
                type: ControlWidgetType::Text,
                description: 'Single-line text input.',
                inputComponent: 'text',
                schema: []
            ),
            $this->template(
                type: ControlWidgetType::Color,
                description: 'Color picker control.',
                inputComponent: 'color',
                schema: ['color_format' => 'hex']
            ),
            $this->template(
                type: ControlWidgetType::Json,
                description: 'JSON payload editor.',
                inputComponent: 'textarea',
                schema: []
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{
     *     key: string,
     *     widget_type: string,
     *     label: string,
     *     description: string,
     *     input_component: string,
     *     supports_realtime: bool,
     *     schema: array<string, mixed>,
     *     is_active: bool
     * }
     */
    private function template(ControlWidgetType $type, string $description, string $inputComponent, array $schema): array
    {
        return [
            'key' => $type->value,
            'widget_type' => $type->value,
            'label' => $type->label(),
            'description' => $description,
            'input_component' => $inputComponent,
            'supports_realtime' => true,
            'schema' => $schema,
            'is_active' => true,
        ];
    }
}
