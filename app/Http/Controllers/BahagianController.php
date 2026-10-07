<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BahagianController extends Controller
{
    public function index()
    {
        $senarai = Bahagian::all();
        return view('bahagian.index', compact('senarai'));
    }

    public function create()
    {
        return view('bahagian.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kod_bahagian' => ['required', Rule::unique('bahagian')->whereNull('deleted_at')],
            'nama_bahagian' => 'required'
        ]);

        Bahagian::create($request->all());
        return redirect()->route('bahagian.index')->with('success', 'Bahagian berjaya didaftarkan!');
    }

    public function edit($id)
    {
        $bahagian = Bahagian::findOrFail($id);
        return view('bahagian.edit', compact('bahagian'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'kod_bahagian' => ['required', Rule::unique('bahagian')->ignore($id, 'bahagian_id')->whereNull('deleted_at')],
            'nama_bahagian' => 'required'
        ]);

        $bahagian = Bahagian::findOrFail($id);
        $bahagian->update($request->all());
        return redirect()->route('bahagian.index')->with('success', 'Maklumat Bahagian berjaya dikemas kini!');
    }

    public function destroy($id)
    {
        $bahagian = Bahagian::findOrFail($id);
        $bahagian->delete();
        return redirect()->route('bahagian.index')->with('success', 'Bahagian telah dipadam.');
    }
}