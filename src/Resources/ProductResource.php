<?php

namespace roilafx\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'products',
            'id' => (string) $this->id,
            'attributes' => [
                'title' => $this->pagetitle,
                'alias' => $this->alias,
                'introtext' => $this->introtext,
                'url' => evo()->makeUrl($this->id),
                'attrs' => $this->attrs ?? [],
            ],
        ];
    }
}