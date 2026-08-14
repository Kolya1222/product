<?php

namespace roilafx\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PresetResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'presets',
            'id' => (string) $this->id,
            'attributes' => [
                'name' => $this->name,
                'description' => $this->description,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'relationships' => [
                'attributes' => [
                    'data' => AttributeResource::collection(
                        $this->whenLoaded('attributes')
                    ),
                ],
            ],
        ];
    }
}