<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use App\Banksoal;
use App\JawabanPeserta;
use App\Jadwal;
use App\SiswaUjian;
use App\HasilUjian;
use App\JawabanSoal;
use App\UjianAktif;
use App\Peserta;

use Carbon\Carbon;

use DB;
use Illuminate\Support\Str;

class UjianController extends Controller
{
    /**
     * Get soal by id
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getsoal(Request $request)
    {
        $peserta = Peserta::find($request->peserta);

        $jadwal = UjianAktif::with(['jadwal'])->first();

        $ids = array_column($jadwal->jadwal->ids, 'jurusan','id');

        $id_banksoal = 'X';
        foreach($ids as $key => $id) {
            if(is_array($id)) {
                foreach($id as $d) {
                    if($d == $peserta->jurusan_id) {

                        $id_banksoal =  $key;
                    }
                }
            } else {
                if($id == 0) {
                    $id_banksoal =  $key;
                }
            }
        }
        $all = Banksoal::with(['pertanyaans','pertanyaans.jawabans'])->where('id',$id_banksoal)->first();

        if(!$all) {
            return response()->json([], 400);
        }

    	return response()->json(['data' => $all]);
    }

    /**
     * Store data ujian to table
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $find = JawabanPeserta::where([
            'id'            => $request->jawaban_id
        ])->first();

        $kj = JawabanSoal::find($request->jawab);

        if(isset($request->essy)) {
            $find->esay = $request->essy;
            $find->save();

            $send = $find->only('id','banksoal_id','soal_id','jawab', 'esay','ragu_ragu');
            
            return response()->json(['data' => $send,'index' => $request->index]);
        }

        $id = UjianAktif::first();   

        $ujian = SiswaUjian::where([
            'jadwal_id'     => $id->ujian_id,
            'peserta_id'    => $find->peserta_id
        ])->first();

        if($ujian) {         
            $deUjian = Jadwal::find($id->ujian_id);
    
            $start = Carbon::createFromFormat('H:i:s', $ujian->mulai_ujian);
            $now = Carbon::createFromFormat('H:i:s', Carbon::now()->format('H:i:s'));
    
            $diff_in_minutes = $start->diffInSeconds($now);
    
            $ujian->sisa_waktu = $deUjian->lama-$diff_in_minutes;
            $ujian->save();
        }

        if(!$kj) {
            $send = $find->only('id','banksoal_id','soal_id','jawab', 'esay','ragu_ragu');
            return response()->json(['data' => $send,'index' => $request->index]);
        }
        $find->jawab = $request->jawab;
        $find->iscorrect = $kj->correct;
        $find->save();

        $send = $find->only('id','banksoal_id','soal_id','jawab', 'esay');

    	return response()->json(['data' => $send,'index' => $request->index]);
    	
    }

    /** 
     * Set ragu ragu in siswa
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function setRagu(Request $request) 
    {
        $find = JawabanPeserta::where([
            'id'            => $request->jawaban_id
        ])->first();

        if(!isset($request->ragu_ragu)) {
            return response()->json(['data' => $send,'index' => $request->index]); 
        }

        $find->ragu_ragu = $request->ragu_ragu;
        $find->save();

        $send = $find->only('id','banksoal_id','soal_id','jawab','esay','ragu_ragu');

        return response()->json(['data' => $send,'index' => $request->index]);
    }

    /**
     * Get jawaban peserta 
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getJawabanPeserta($id)
    {
        $data = JawabanPeserta::where(['soal_id' => $id])->first();
        $data = $data->only('id','banksoal_id','soal_id','jawab','esay','ragu_ragu');
        return response()->json(['data' => $data]);
    }

    /**
     * Get list ujian
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function getListUjian()
    {
        $data = Jadwal::with('banksoal')->where(['tanggal' => now()->format('Y-m-d')])->get();
        
        return response()->json(['data' => $data]);
    }

    /**
     * Store or get the JawabanPeserta table
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function filled(Request $request)
    {
        $user = request()->get('peserta-auth');

        $id = $request->banksoal;
        $jadwal_id = $request->jadwal_id;
        $user_id = $user->id;
        
        $find = JawabanPeserta::with([
          'soal' => function($q) {
            $q->select('id','banksoal_id','pertanyaan','tipe_soal','audio','direction'); 
        },'soal.jawabans' => function($q) {
            $q->select('id','soal_id','text_jawaban')
            ->inRandomOrder();
	    }
        ])->where([
            'peserta_id'    => $user_id,
            'jadwal_id'     => $jadwal_id,
        ])
        ->select('id','banksoal_id','soal_id','jawab','esay','ragu_ragu')
        ->get()
        ->makeHidden('similiar');

        if ($find->count() < 1 ) {

            $all = Banksoal::with(['pertanyaans','pertanyaans.jawabans'])->where('id',$id)->first();

            $max_soal = $all->jumlah_soal;
            $max_essay = $all->jumlah_soal_esay;
            $i = 1;

            foreach($all->pertanyaans as $p) {
                if($p->tipe_soal != 3) {
                    continue;
                }
                JawabanPeserta::create([
                    'peserta_id'    => $user_id, 
                    'banksoal_id'   => $id, 
                    'soal_id'       => $p->id, 
                    'jawab'         => 0, 
                    'iscorrect'     => 0,
                    'jadwal_id'     => $jadwal_id,
                    'ragu_ragu'     => 0
                ]);
                if ($i++ == $max_soal) break;
            }

            $collection = new Collection($all->pertanyaans);
            $perta = $collection->shuffle();

            if($perta != null) {
                foreach($perta as $p) {
                    if($p->tipe_soal != 1) {
                        continue;
                    }
                    JawabanPeserta::create([
                        'peserta_id'    => $user_id, 
                        'banksoal_id'   => $id, 
                        'soal_id'       => $p->id, 
                        'jawab'         => 0, 
                        'iscorrect'     => 0,
                        'jadwal_id'     => $jadwal_id,
                        'ragu_ragu'     => 0
                    ]);

                    if ($i++ == $max_soal) break;
                }
            }

            if ($max_essay != null && $max_essay > 0) {
                foreach($perta as $p) {
                    if($p->tipe_soal != 2) {
                        continue;
                    }
                    
                    JawabanPeserta::create([
                        'peserta_id'    => $user_id, 
                        'banksoal_id'   => $id, 
                        'soal_id'       => $p->id, 
                        'jawab'         => 0, 
                        'iscorrect'     => 0,
                        'jadwal_id'     => $jadwal_id,
                        'ragu_ragu'     => 0
                    ]);
    
                    if ($i++ == $max_essay) break;
                }
            }
            
            $detail = SiswaUjian::where([
                'jadwal_id'     => $jadwal_id,
                'peserta_id'    => $user_id
            ])->first();

            
            $find = JawabanPeserta::with([
                'soal' => function($q) {
                    $q->select('id','banksoal_id','pertanyaan','tipe_soal','audio','direction'); 
                },'soal.jawabans' => function($q) {
                    $q->select('id','soal_id','text_jawaban')
                    ->inRandomOrder();
                }
            ])->where([
                'peserta_id'    => $user_id, 
                'jadwal_id'     => $jadwal_id,
            ])
            ->select('id','banksoal_id','soal_id','jawab', 'esay','ragu_ragu')
            ->get()
            ->makeHidden('similiar');

          
	  /**
            $find = JawabanPeserta::with([
                'soal','soal.jawabans'
            ])->where([
                'peserta_id'    => $user_id, 
                'jadwal_id'     => $jadwal_id,
            ])->get();
    		**/
            return response()->json(['data' => $find, 'detail' => $detail]);
        }
        
        $ujian = SiswaUjian::where([
            'jadwal_id'     => $jadwal_id,
            'peserta_id'    => $user_id
        ])->first();

        $deUjian = Jadwal::find($request->jadwal_id);

        $start = Carbon::createFromFormat('H:i:s', $ujian->mulai_ujian);
        $now = Carbon::createFromFormat('H:i:s', Carbon::now()->format('H:i:s'));

        $diff_in_minutes = $start->diffInSeconds($now);

        if($diff_in_minutes > $deUjian->lama) {
    
            $ujian->status_ujian = 1;
            $ujian->save();
            
            $salah = JawabanPeserta::where([
                'iscorrect'     => 0,
                'jadwal_id'     => $request->jadwal_id, 
                'peserta_id'    => $user->id,
            ])
            ->whereHas('soal', function($query) {
                $query->where('tipe_soal','!=', '2');
            })
            ->count();

            $benar = JawabanPeserta::where([
                'iscorrect'     => 1,
                'jadwal_id'     => $request->jadwal_id, 
                'peserta_id'    => $user->id
            ])
            ->count();
            
            $jml = JawabanPeserta::where([
                'jadwal_id'     => $request->jadwal_id, 
                'peserta_id'    => $user->id
            ])
            ->whereHas('soal', function($query) {
                $query->where('tipe_soal','!=', '2');
            })
            ->count();

            $null = JawabanPeserta::where([
                'jawab'     => 0,
                'jadwal_id'     => $request->jadwal_id, 
                'peserta_id'    => $peserta->id,
            ])
            ->whereHas('soal', function($query) {
                $query->where('tipe_soal','!=', '2');
            })
            ->count();
    
            $hasil = ($benar/$jml)*70;
    
            HasilUjian::create([
                'banksoal_id'     => $id,
                'peserta_id'      => $user->id,
                'jadwal_id'       => $request->jadwal_id,
                'jumlah_salah'    => $salah,
                'jumlah_benar'    => $benar,
                'tidak_diisi'     => $null,
                'hasil'           => $hasil,
                'point_esay'      => 0
            ]);
            
            return response()->json(['data' => $find, 'detail' => $ujian]);
        }
        
        $ujian->sisa_waktu = $deUjian->lama-$diff_in_minutes;
        $ujian->save();

        return response()->json(['data' => $find, 'detail' => $ujian]);
    }

    /**
     * Get sisa waktu
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function sisaWaktu(Request $request)
    {
        $user = request()->get('peserta-auth');

        $detail = SiswaUjian::where([
            'jadwal_id'     => $request->jadwal_id,
            'peserta_id'    => $user->id
        ])->first();
        $detail->sisa_waktu = $request->sisa_waktu;
        $detail->save();
        return response()->json(['data' => 'updated']);
    }

    /**
     * Detail ujian
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function detUjian(Request $request) 
    {
        $peserta = request()->get('peserta-auth');
        $jadwal = UjianAktif::first();

        $relate = Jadwal::find($jadwal->ujian_id); 

        $ujian = SiswaUjian::where([
            'jadwal_id'     => $relate->id, 
            'peserta_id'    => $peserta['id']
        ])->first();

        if(!$ujian) {
            $data = [
                'jadwal_id'     => $relate->id,
                'peserta_id'    => $peserta['id'],
                'mulai_ujian'   => '',
                'sisa_waktu'    => $relate->lama,
                'status_ujian'  => 0,
                'uploaded'      => 0
            ];

            $data = SiswaUjian::create($data);

            return response()->json(['data' => $data]);
        }

        if(!$ujian->mulai_ujian) {
            return response()->json(['data' => $ujian]);
        }

        $start = Carbon::createFromFormat('H:i:s', $ujian->mulai_ujian);
        $now = Carbon::createFromFormat('H:i:s', Carbon::now()->format('H:i:s'));

        $diff_in_minutes = $start->diffInSeconds($now);

        if($diff_in_minutes > $relate->lama) {
            return response()->json(['data' => $ujian]);
        }
        
        $ujian->sisa_waktu = $relate->lama-$diff_in_minutes;
        $ujian->save();

        return response()->json(['data' => $ujian]);
    }

    /**
     * Finish
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function selesai(Request $request)
    {
        $peserta = request()->get('peserta-auth');

	    $aktif = UjianAktif::first();
        $ujian = SiswaUjian::where([
            'jadwal_id'     => $aktif->ujian_id, 
            'peserta_id'    => $peserta->id
        ])->first();


        $hasilUjian = HasilUjian::where([
            'peserta_id'    => $peserta->id,
            'jadwal_id'     => $aktif->ujian_id,
        ])->first();

        if($hasilUjian) {
            return response()->json(['status' => 'finished']); 
        }

        $ujian->status_ujian = 1;
        $ujian->save();

        $banksoal = JawabanPeserta::where([
            'jadwal_id'     => $aktif->ujian_id, 
            'peserta_id'    => $peserta->id
        ])->first();

        $salah = JawabanPeserta::where([
            'iscorrect'     => 0,
            'jadwal_id'     => $request->jadwal_id, 
            'peserta_id'    => $peserta->id,
        ])
        ->whereHas('soal', function($query) {
            $query->where('tipe_soal','!=', '2');
        })
        ->count();

        $benar = JawabanPeserta::where([
            'iscorrect'     => 1,
            'jadwal_id'     => $aktif->ujian_id, 
            'peserta_id'    => $peserta->id
        ])->count();
        
        $jml = JawabanPeserta::where([
            'jadwal_id'     => $request->jadwal_id, 
            'peserta_id'    => $peserta->id
        ])
        ->whereHas('soal', function($query) {
            $query->where('tipe_soal','!=', '2');
        })
        ->count();

        $null = JawabanPeserta::where([
            'jawab'     => 0,
            'jadwal_id'     => $request->jadwal_id, 
            'peserta_id'    => $peserta->id,
        ])
        ->whereHas('soal', function($query) {
            $query->where('tipe_soal','!=', '2');
        })
        ->count();

        $hasil = ($benar/$jml)*100;

        HasilUjian::create([
            'banksoal_id'     => $banksoal->banksoal_id,
            'peserta_id'      => $peserta->id,
            'jadwal_id'       => $aktif->ujian_id,
            'jumlah_salah'    => $salah,
            'jumlah_benar'    => $benar,
            'tidak_diisi'     => $null,
            'hasil'           => $hasil,
            'point_esay'      => 0.0
        ]);

        return response()->json(['status' => 'finished']);
    }

    /**
     * Check token
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function cekToken(Request $request)
    {
        $jadwal = UjianAktif::first();
        if($jadwal) {
            $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', now());
            $from = $jadwal['updated_at']->format('Y-m-d H:i:s');
            $differ = $to->diffInSeconds($from);

            if($differ > 900) {
                $jadwal->token = strtoupper(Str::random(6));
                $jadwal->status_token = 0;
                $jadwal->save();
            }  
        }
        if($jadwal->token == $request->token) {
            if($jadwal->status_token != 1) {
                return response()->json(['message' => 'Status token belum aktif'], 400);
            }
            return response()->json(['message' =>'success']);
        }
        return response()->json(['message' => 'Token tidak sesuai'], 400);
    }

    /**
     *  Get ujian aktif
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function getUjianAktif()
    {
        $peserta = request()->get('peserta-auth');

        $jadwal = UjianAktif::with(['jadwal' => function($query) {
            $query->select('id','alias','lama','mulai','banksoal_id');
        }])->first()
        ->makeHidden('token')
        ->makeHidden('status_token')
        ->makeHidden('created_at')
        ->makeHidden('updated_at');

        $ids = array_column($jadwal->jadwal->banksoal_id, 'jurusan','id');

        $id_banksoal = '';
        foreach($ids as $key => $id) {
            $bks = Banksoal::with('matpel')->where('id', $key)->first();

            if($bks) {
                if($bks->matpel->agama_id != 0) {
                    if($bks->matpel->agama_id == $peserta['agama_id']) {
                        $id_banksoal = $key;
                        break;
                    }
                } else {
                    if(is_array($id)) {
                        foreach($id as $d) {
                            if($d == $peserta['jurusan_id']) {

                                $id_banksoal =  $key;
                                break;
                            }
                        }
                    } else {
                        if($id == 0) {
                            $id_banksoal =  $key;
                            break;
                        }
                    }
                }
            }
        } 
        $banksoal = Banksoal::with('matpel')->where('id',$id_banksoal)->first();

        $jadwal = $jadwal->toArray();
        $jadwal['matpel'] = $banksoal->matpel->nama;
        $jadwal['banksoal_id'] = $banksoal->id;
        return response()->json(['data' => $jadwal]);
    }

    /**
     * Start
     *
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\Response
     */
    public function mulaiPeserta(Request $request)
    {
        $siswa = request()->get('peserta-auth');

        $jadwal = UjianAktif::first();
        $peserta = SiswaUjian::where([
            'peserta_id' => $siswa['id'], 
            'jadwal_id' => $jadwal->ujian_id
        ])->first();

        if($peserta->status_ujian != 3) {
            $peserta->mulai_ujian = now()->format('H:i:s');
            $peserta->status_ujian = 3;
            $peserta->save();
            return response()->json(['status' => 'save']);
        }
        
        return response()->json(['status' => 'save']);
    }
}
 
