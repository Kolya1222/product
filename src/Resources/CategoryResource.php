<?php

namespace roilafx\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'categories',
            'id' => (string) $this->id,
            'attributes' => [
                'name' => $this->name,
                'created_at' => $this->created_at ?? null,
                'updated_at' => $this->updated_at ?? null,
            ],
            'relationships' => [
                'attributes' => [
                    'data' => isset($this->attributes)
                        ? AttributeResource::collection($this->attributes)
                        : [],
                ],
            ],
        ];
    }
}
