<?php

namespace roilafx\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'attributes',
            'id' => (string) $this->id,
            'attributes' => [
                'name' => $this->name,
                'code' => $this->code,
                'field_type' => $this->field_type,
                'options' => $this->options,
                'assigned' => $this->when(isset($this->assigned), $this->assigned),
                'value' => $this->when(isset($this->value), $this->value),
            ],
            'relationships' => [
                'category' => [
                    'data' => $this->category ? [
                        'type' => 'categories',
                        'id' => (string) $this->category->id,
                    ] : null,
                ],
            ],
        ];
    }
}