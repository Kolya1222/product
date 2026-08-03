<?php

namespace roilafx\Product\Validators;

use roilafx\Product\Helpers\TvTypeHelper;

class UpdateAttributeValidator
{
    public function rules(int $attributeId): array
    {
        $allTypes = $this->getAllTvTypes();
        $optionTypes = ['select', 'dropdown', 'listbox', 'listbox-multiple', 'option', 'checkbox'];

        return [
            'name'        => 'required|string',
            'code'        => 'required|string|unique:attributes,code,' . $attributeId,
            'field_type'  => 'required|in:' . implode(',', $allTypes),
            'options'     => 'required_if:field_type,' . implode(',', $optionTypes) . '|array|min:1',
            'category_id' => 'nullable|exists:attribute_categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Атрибут с таким кодом уже существует',
        ];
    }

    private function getAllTvTypes(): array
    {
        $types = [];
        foreach (TvTypeHelper::forSelect() as $group) {
            foreach ($group['optgroup']['options'] as $value => $label) {
                $types[] = $value;
            }
        }
        return $types;
    }
}