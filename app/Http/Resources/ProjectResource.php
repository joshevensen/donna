<?php

namespace App\Http\Resources;

use App\Enums\PersonKind;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tier' => $this->tier,
            'status' => $this->status,
            // Josh's own projects need no owner; only someone else's is named.
            'owner' => $this->owner?->kind === PersonKind::Self ? null : $this->owner?->name,
        ];
    }
}
