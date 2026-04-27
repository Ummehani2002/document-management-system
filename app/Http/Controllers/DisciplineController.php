<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{
    public function index()
    {
        $disciplines = Discipline::orderBy('name')->paginate(15);

        return view('disciplines.index', compact('disciplines'));
    }

    public function create()
    {
        return view('disciplines.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:disciplines,name',
        ]);
        Discipline::create($request->only('name'));

        return redirect()->route('disciplines.index')->with('success', 'Discipline created.');
    }

    public function edit(Discipline $discipline)
    {
        return view('disciplines.edit', compact('discipline'));
    }

    public function update(Request $request, Discipline $discipline)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:disciplines,name,' . $discipline->id,
        ]);
        $discipline->update($request->only('name'));

        return redirect()->route('disciplines.index')->with('success', 'Discipline updated.');
    }

    public function destroy(Discipline $discipline)
    {
        $discipline->delete();

        return redirect()->route('disciplines.index')->with('success', 'Discipline deleted.');
    }
}
