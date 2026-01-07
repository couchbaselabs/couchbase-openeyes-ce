<?php

namespace OE\factories\models;

use OE\factories\ModelFactory;

/**
 * Generic fallback factory for models without a dedicated Factory class.
 *
 * This is primarily intended for CypressHelper endpoints and verification tooling.
 */
class GenericActiveRecordFactory extends ModelFactory
{
    private string $modelClass;

    public static function forModel(string $modelClass): self
    {
        $factory = new self();
        $factory->modelClass = ltrim($modelClass, '\\');
        return $factory->configure();
    }

    public function modelName()
    {
        return $this->modelClass;
    }

    public function definition(): array
    {
        $modelClass = $this->modelName();

        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        $schema = method_exists($model, 'getMetaData') ? $model->getMetaData()->columns : [];
        $rules = method_exists($model, 'rules') ? $model->rules() : [];

        $required = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || count($rule) < 2) {
                continue;
            }

            $attrs = $rule[0] ?? '';
            $type = $rule[1] ?? '';
            if ($type !== 'required' || !is_string($attrs)) {
                continue;
            }

            foreach (preg_split('/\s*,\s*/', trim($attrs)) as $attr) {
                if ($attr !== '') {
                    $required[$attr] = true;
                }
            }
        }

        $defaults = [];

        // Common fields across OpenEyes models
        foreach (['active' => '1'] as $k => $v) {
            if (isset($schema[$k])) {
                $defaults[$k] = $v;
            }
        }

        foreach (array_keys($required) as $attr) {
            if (!isset($schema[$attr])) {
                // best-effort fallback
                if (in_array($attr, ['name', 'change', 'term', 'description'], true)) {
                    $defaults[$attr] = $this->faker->words(3, true);
                }
                continue;
            }

            $column = $schema[$attr];
            $type = strtolower((string) ($column->dbType ?? $column->type ?? ''));
            $maxLength = $column->size ?? null;

            if (str_contains($type, 'int')) {
                $defaults[$attr] = (string) $this->faker->numberBetween(1, 100);
            } elseif (str_contains($type, 'bool') || $type === 'tinyint(1)') {
                $defaults[$attr] = '1';
            } elseif (str_contains($type, 'date') || str_contains($type, 'time')) {
                $defaults[$attr] = date('Y-m-d H:i:s');
            } else {
                $value = $this->faker->words(3, true);
                if (is_int($maxLength) && $maxLength > 0) {
                    $value = substr($value, 0, $maxLength);
                }
                $defaults[$attr] = $value;
            }
        }

        return $defaults;
    }
}
