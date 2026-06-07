<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\Request;

class SessionShotBoardController extends Controller
{
    public function __invoke(Request $request, Session $session)
    {
        // Authorize access
        abort_unless($session->user_id === auth()->id(), 403);

        return view('sessions.shot-board', [
            'session' => $session,
        ]);
    }
}
