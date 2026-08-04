<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'in:id,en'],
        ]);

        $locale = $request->input('locale', 'id');

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }

        cookie()->queue('locale', $locale, 60 * 24 * 365);

        return redirect()->back()->with('success', __('messages.language.changed'));
    }
}
