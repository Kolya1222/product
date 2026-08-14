<?php

namespace roilafx\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'type' => 'variants',
            'id' => (string) $this->id,
            'attributes' => [
                'product_id' => $this->product_id,
                'sort' => $this->sort,
                'active' => (bool) $this->active,
                'created_at' => $this->created_at ?? null,
                'updated_at' => $this->updated_at ?? null,
            ],
        ];

        if (isset($this->attrs_json)) {
            $data['attributes']['attrs'] = json_decode($this->attrs_json, true);
        }

        if (isset($this->attributeValues) && !empty($this->attributeValues)) {
            $data['relationships']['attribute_values'] = [
                'data' => VariantAttributeValueResource::collection($this->attributeValues),
            ];
        }

        if ($this->product_id) {
            $data['relationships']['product'] = [
                'data' => [
                    'type' => 'products',
                    'id' => (string) $this->product_id,
                ],
            ];
        }

        return $data;
    }
}