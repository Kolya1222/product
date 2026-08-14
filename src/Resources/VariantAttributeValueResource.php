<?php

namespace roilafx\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VariantAttributeValueResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'variant-attribute-values',
            'id' => (string) $this->id,
            'attributes' => [
                'value' => $this->value,
                'value_numeric' => $this->value_numeric,
            ],
            'relationships' => [
                'attribute' => [
                    'data' => new AttributeResource($this->whenLoaded('attribute')),
                ],
            ],
        ];
    }
}