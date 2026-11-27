<?php

namespace App\Http\Resources;

use App\Models\EvalExample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EvalExample
 */
class EvalExampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message_id' => $this->message_id,
            'rating' => $this->rating,
            'note' => $this->note,
        ];
    }
}
