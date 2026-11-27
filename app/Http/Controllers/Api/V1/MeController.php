<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Who is signed in, the timezone dates are shown in, and whether push is set up.
     */
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user());
    }
}
