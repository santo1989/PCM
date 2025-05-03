<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return view('backend.library.categories.index', compact('categories'));
    }


    public function create()
    {
        $categories = Category::all();
        return view('backend.library.categories.create', compact('categories'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3|max:191',
            'types' => 'required',
            'rules' => 'required',
        ]);

        $categories = Category::all();
        // Data insert
        $categories = new Category;
        $categories->name = $request->name;
        $categories->types = $request->types;
        if ($request->types == 'expense') // if types is 'expense
        {
            $categories->rules = $request->rules;
        } elseif ($request->types == 'Loan' || $request->types == 'Return') { // if types is 'income
            $categories->rules = $request->rules;
        } else {
            $categories->rules = '0';
        }


        $categories->save();

        // Redirect
        return redirect()->route('categories.index');
    }


    public function show($id)
    {
        $categories = Category::findOrFail($id);
        return view('backend.library.categories.show', compact('categories'));
    }


    public function edit($id)
    {
        $categories = Category::findOrFail($id);
        return view('backend.library.categories.edit', compact('categories'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|min:3|max:191',
            'types' => 'required',
            'rules' => 'required',
        ]);

        // Data update
        $categories = Category::findOrFail($id);
        $categories->name = $request->name;
        $categories->types = $request->types;
        if ($request->types == 'expense') // if types is 'expense
        {
            $categories->rules = $request->rules;
        } elseif ($request->types == 'Loan' || $request->types == 'Return') { // if types is 'income
            $categories->rules = $request->rules;
        } else {
            $categories->rules = '0';
        }
        $categories->save();

        // Redirect
        return redirect()->route('categories.index');
    }


    public function destroy($id)
    {
        $categories = Category::findOrFail($id)->delete();

        return redirect()->route('categories.index')->withMessage('Category are deleted successfully!');
    }
}
