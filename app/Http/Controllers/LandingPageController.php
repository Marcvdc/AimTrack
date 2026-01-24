<?php

namespace App\Http\Controllers;

use App\Support\Landing\LandingPageData;
use Illuminate\Contracts\View\View;

class LandingPageController extends Controller
{
    public function __invoke(LandingPageData $landingPageData): View
    {
        return view('welcome', [
            'stats' => $landingPageData->stats(),
            'knsaLinks' => $landingPageData->knsaLinks(),
        ]);
    }
}
