<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Push\Presence;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PresenceController extends Controller
{
    /**
     * The app was hidden or closed: stop treating Josh as watching.
     */
    public function destroy(Request $request, Presence $presence): Response
    {
        $presence->left($request->user());

        return response()->noContent();
    }
}
