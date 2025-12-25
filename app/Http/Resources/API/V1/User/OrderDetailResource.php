<?php

namespace App\Http\Resources\API\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'name' => $this->name,
            'industry' => $this->industry,
            'requirement_description' => $this->requirement_description,
            'voice_preference' => $this->voice_preference,
            'attachments' => $this->attachments,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
