<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PersonKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonController extends Controller
{
    /**
     * The people Donna knows about, by name, for `/people`: everyone but Josh and Donna.
     */
    public function index(): AnonymousResourceCollection
    {
        $people = Person::query()
            ->where('kind', PersonKind::Other)
            ->orderByRaw('lower(name)')
            ->get();

        return PersonResource::collection($people);
    }
}
