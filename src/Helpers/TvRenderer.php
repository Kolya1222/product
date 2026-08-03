<?php

namespace roilafx\Product\Helpers;

use roilafx\Product\Models\Attribute;

class TvRenderer
{
    public static function render(Attribute $attribute, string $value = ''): string
    {
        $row = [
            'type'         => $attribute->field_type,
            'id'           => $attribute->id,
            'default_text' => $attribute->default_text ?? '',
            'elements'     => is_array($attribute->options)
                ? implode('||', $attribute->options)
                : ($attribute->elements ?? ''),
            'properties'   => $attribute->properties ?? [],
            'name'         => $attribute->code,
            'caption'      => $attribute->name,
            'description'  => $attribute->description ?? '',
            'display'      => $attribute->display ?? '',
            'display_params' => $attribute->display_params ?? '',
        ];

        $fieldType = $attribute->field_type;
        $processedValue = $value;

        if (str_starts_with($fieldType, 'custom_tv:multitv')) {
            $fieldNames = [];
            if (!empty($row['properties']['fieldnames'])) {
                $fieldNames = array_map('trim', explode(',', $row['properties']['fieldnames']));
            }
            if (empty($fieldNames)) {
                $fieldNames = ['value'];
            }

            if (empty($processedValue) || $processedValue === '[]') {
                $processedValue = json_encode(['fieldValue' => []]);
            } else {
                $decoded = json_decode($processedValue, true);
                if (is_array($decoded) && !array_key_exists('fieldValue', $decoded)) {
                    $processedValue = json_encode(['fieldValue' => $decoded]);
                } elseif (!is_array($decoded) || !array_key_exists('fieldValue', $decoded)) {
                    $item = array_fill_keys($fieldNames, '');
                    $lastKey = end($fieldNames);
                    $item[$lastKey] = $processedValue;
                    $processedValue = json_encode(['fieldValue' => [$item]]);
                }
            }

            if (!empty($row['properties']['display'])) {
                $row['display'] = $row['properties']['display'];
            } elseif (empty($row['display'])) {
                $row['display'] = 'vertical';
            }

            $row['value'] = $processedValue;
        }

        $html = \renderFormElement(
            $row['type'],
            $row['id'],
            $row['default_text'],
            $row['elements'],
            $processedValue,
            '',
            $row,
            [],
            null
        );

        $html = str_replace('tv' . $row['id'], 'attrs_' . $row['name'], $html);
        $html = str_replace('name="attrs_' . $row['name'] . '"', 'name="attrs[' . $row['name'] . ']"', $html);
        $html = str_replace("$j('#attrs_" . $row['name'] . "')", "$j('#attrs_" . $row['name'] . "')", $html);
        $html = str_replace('tv_' . $row['id'], 'attrs_' . $row['name'], $html);

        return $html;
    }
}
