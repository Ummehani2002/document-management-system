<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\Request;

class EntityController extends Controller
{
    public function index()
    {
        $entities = Entity::withCount('projects')->orderBy('name')->paginate(15);
        return view('entities.index', compact('entities'));
    }

    public function create()
    {
        return view('entities.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        Entity::create($request->only('name'));
        return redirect()->route('entities.index')->with('success', 'Entity created.');
    }

    public function edit(Entity $entity)
    {
        return view('entities.edit', compact('entity'));
    }

    public function update(Request $request, Entity $entity)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $entity->update($request->only('name'));
        return redirect()->route('entities.index')->with('success', 'Entity updated.');
    }

    public function destroy(Entity $entity)
    {
        $entity->delete();
        return redirect()->route('entities.index')->with('success', 'Entity deleted.');
    }
}
