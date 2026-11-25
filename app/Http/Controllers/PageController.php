<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function login(): View
    {
        return view('pages.index');
    }

    public function show(string $page): View
    {
        return view('pages.'.$page);
    }
}
