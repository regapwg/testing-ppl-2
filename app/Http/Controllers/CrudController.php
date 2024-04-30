<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\KRS;
use App\Models\KRSDetail;
use App\Models\Mahasiswa;
use App\Models\Matakuliah;

class CrudController extends Controller
{
    public function index()
    {
        $icon = 'ni ni-dashlite';
        $subtitle = 'List KHS Mahasiswa';
        $table_id = 'krs_detail';
        
        $data = KRSDetail::all();
        $data = $this->calculateNilaiHuruf($data);

        return view('welcome', compact('subtitle', 'table_id', 'icon', 'data'));
    }

    private function calculateNilaiHuruf($data)
    {
        return $data->map(function ($item, $key) {
            if ($item->nilai_akhir !== null) {
                if ($item->nilai_akhir >= 80) {
                    $item->nilai_huruf = 'A';
                } elseif ($item->nilai_akhir >= 71) {
                    $item->nilai_huruf = 'B+';
                } elseif ($item->nilai_akhir >= 66) {
                    $item->nilai_huruf = 'B';
                } elseif ($item->nilai_akhir >= 60) {
                    $item->nilai_huruf = 'C+';
                } elseif ($item->nilai_akhir >= 55) {
                    $item->nilai_huruf = 'C';
                } elseif ($item->nilai_akhir >= 50) {
                    $item->nilai_huruf = 'D+';
                } elseif ($item->nilai_akhir >= 45) {
                    $item->nilai_huruf = 'D';
                } else {
                    $item->nilai_huruf = 'E';
                }
            } else {
                $item->nilai_huruf = ''; 
            }
            return $item;
        });
    }

    public function search(Request $request)
    {
        // Get the NIM from the request
        $cariNIM = $request->input('cariNIM');

        // Search for KRSDetail with the given NIM and eager load the relationships
        $searchResult = KRSDetail::whereHas('krs.mahasiswa', function ($query) use ($cariNIM) {
                $query->where('nim', $cariNIM);
            })
            ->with(['krs.krsDetail.matakuliah', 'krs.mahasiswa'])
            ->get(['krs_id', 'matakuliah_id', 'nilai_akhir']);

        // If no result found, return an empty array
        if ($searchResult->isEmpty()) {
            $searchResult = [];
        }
        
        // dd($searchResult);

        // Pass the search result to the view
        return view('search_result', compact('searchResult'));
    }

    // public function index(){
    //     $icon = 'ni ni-dashlite';
    //     $subtitle = 'List KHS Mahasiswa';
    //     $table_id = 'krs_detail';

    //     return view('welcome',compact('subtitle','table_id','icon'));
    // }

    // public function semualistData(Request $request){
    //     $data = KRSDetail::all();
        
    //     // Calculate nilai_huruf for each row
    //     $data->transform(function ($item, $key) {
    //         if ($item->nilai_akhir !== null) {
    //             if ($item->nilai_akhir >= 80) {
    //                 $item->nilai_huruf = 'A';
    //             } elseif ($item->nilai_akhir >= 71) {
    //                 $item->nilai_huruf = 'B+';
    //             } elseif ($item->nilai_akhir >= 66) {
    //                 $item->nilai_huruf = 'B';
    //             } elseif ($item->nilai_akhir >= 60) {
    //                 $item->nilai_huruf = 'C+';
    //             } elseif ($item->nilai_akhir >= 55) {
    //                 $item->nilai_huruf = 'C';
    //             } elseif ($item->nilai_akhir >= 50) {
    //                 $item->nilai_huruf = 'D+';
    //             } elseif ($item->nilai_akhir >= 45) {
    //                 $item->nilai_huruf = 'D';
    //             } else {
    //                 $item->nilai_huruf = 'E';
    //             }
    //         } else {
    //             $item->nilai_huruf = ''; 
    //         }
    //         return $item;
    //     });
    
    //     $datatables = DataTables::of($data);
    //     return $datatables
    //         ->addIndexColumn()
    //         ->addColumn('nama_krs', function($row) {
    //             return $row->krs->nama_krs;
    //         })
    //         ->addColumn('nama_mk', function($row) {
    //             return $row->matakuliah->nama_mk;
    //         })
    //         ->addColumn('nama_mahasiswa', function($row) {
    //             return $row->krs->mahasiswa->nama;
    //         })
    //         ->addColumn('nilai_huruf', function($row) {
    //             return $row->nilai_huruf;
    //         })
    //         ->addColumn('aksi', function($data){
    //             $aksi = "";
    //             //  $aksi .= "<a title='Edit Data' href='/admin/krs/".$data->id_diklat."/edit' class='btn btn-md btn-primary' data-toggle='tooltip' data-placement='bottom' onclick='buttonsmdisable(this)'><i class='ti-pencil' ></i></a>";
    //             $aksi .= "<a title='Delete Data' href='javascript:void(0)' onclick='deleteData(\"{$data->id}\",this)' class='btn btn-md btn-danger' data-id='{$data->id}'><i class='ti-trash' data-toggle='tooltip' data-placement='bottom' ></i></a> ";
    //             return $aksi;
    //         })
    //         ->rawColumns(['aksi'])
    //         ->make(true);
    // }

    public function cekIPK(Request $request)
    {
        $mahasiswa = Mahasiswa::where('nim', $request->nim)->first();

        if (!$mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        $krsList = KRS::where('mahasiswa_id', $mahasiswa->id)->get();

        if ($krsList->isEmpty()) {
            return response()->json(['message' => 'Tidak ada KRS ditemukan untuk mahasiswa ini'], 404);
        }

        $krsIds = $krsList->pluck('id')->toArray();
        $krsDetails = KRSDetail::with('matakuliah')->whereIn('krs_id', $krsIds)->get();

        $totalSks = 0;
        $weightedSum = 0;

        foreach ($krsDetails as $krsDetail) {
            $nilaiAkhir = $krsDetail->nilai_akhir;

            if ($nilaiAkhir !== null) {
                $grade = match (true) {
                    $nilaiAkhir >= 80 => 4.0,
                    $nilaiAkhir >= 71 => 3.6,
                    $nilaiAkhir >= 66 => 3.2,
                    $nilaiAkhir >= 60 => 2.8,
                    $nilaiAkhir >= 55 => 2.4,
                    $nilaiAkhir >= 50 => 2.0,
                    $nilaiAkhir >= 45 => 1.6,
                    default => 1.2,
                };
            } else {
                continue;
            }

            $sks = $krsDetail->matakuliah->sks;

            $totalSks += $sks;
            $weightedSum += ($sks * $grade);
        }

        $ipk = ($totalSks > 0) ? ($weightedSum / $totalSks) : null;
        $roundedIPK = number_format($ipk, 1);

        if (substr($roundedIPK, -1) === '.') {
            $roundedIPK .= '0';
        }

        // $response = ['success' => true, 'msg' => 'IPK Anda: ' . $roundedIPK];
        
        // return response()->json($response);
        return view('ipk_result', compact('roundedIPK'));
    }
}
