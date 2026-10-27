<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BahagianController extends Controller
{
    public function index()
    {
        $senarai = Bahagian::withCount(['users', 'dokumen', 'pendaftaranMenunggu'])->get();
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
        $bahagian = Bahagian::withCount(['users', 'dokumen', 'pendaftaranMenunggu'])->findOrFail($id);

        // Kakitangan aktif mesti berada dalam bahagian aktif (#15): sekat sehingga dipindahkan.
        // Dokumen tidak menghalang; ia kekal memaparkan nama bahagian (withTrashed).
        if ($bahagian->users_count || $bahagian->pendaftaran_menunggu_count) {
            $sebab = array_filter([
                $bahagian->users_count ? "{$bahagian->users_count} kakitangan aktif" : null,
                $bahagian->pendaftaran_menunggu_count ? "{$bahagian->pendaftaran_menunggu_count} permohonan pendaftaran yang menunggu" : null,
            ]);

            return redirect()->route('bahagian.index')->with('error',
                "Bahagian \"{$bahagian->nama_bahagian}\" tidak boleh dipadam kerana masih ada " . implode(' dan ', $sebab)
                . '. Pindahkan kakitangan ke bahagian lain atau proses permohonan tersebut dahulu.');
        }

        $bahagian->delete();

        $nota = $bahagian->dokumen_count
            ? " {$bahagian->dokumen_count} dokumen sedia ada kekal dalam arkib dengan nama bahagian ini."
            : '';

        return redirect()->route('bahagian.index')->with('success', 'Bahagian telah dipadam.' . $nota);
    }
}