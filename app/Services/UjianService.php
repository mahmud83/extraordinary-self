<?php

/**
 * -----------------------------------------------
 * Ujian Service
 * @since 1.4 Esresso
 * @author shellrean <wandinak17@gmail.com>
 * -----------------------------------------------
 */
namespace App\Services;

use App\Ujian;
use App\Jadwal;
use App\Peserta;
use App\Banksoal;
use Carbon\Carbon;
use App\SiswaUjian;
use App\HasilUjian;
use App\JawabanPeserta;
use Illuminate\Support\Arr;

class UjianService
{
    /**
     * Create new ujian
     * @param  array $data [description]
     * @return array       [description]
     */
	public static function createNew(array $data)
    {
        try {
            Ujian::create($data);
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        return ['success' => true, 'message' => 'Success to create new ujian'];
    }

    /**
     * [getBanksoalPeserta description]
     * @param  Banksoal $banksoal [description]
     * @param  array    $peserta  [description]
     * @return [type]             [description]
     */
    public static function getBanksoalPeserta(Banksoal $banksoal, Peserta $peserta)
    {
        $banksoal_id = '';
        try {
            if($banksoal->matpel->agama_id != 0) {
                if($banksoal->matpel->agama_id == $peserta['agama_id']) {
                    $banksoal_id = $banksoal->id;
                }
            } else {
                if(is_array($banksoal->matpel->jurusan_id)) {
                    foreach ($banksoal->matpel->jurusan_id as $d) {
                        if($d == $peserta['jurusan_id']) {
                            $banksoal_id = $banksoal->id;
                        }
                    }
                } else {
                    if($banksoal->matpel->jurusan_id == 0) {
                        $banksoal_id = $banksoal->id;
                    }
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'messge' => $e->getMessage()];
        }

        return ['success' => true, 'data' => $banksoal_id];
    }

    /**
     * [getJawabanPeserta description]
     * @param  [type] $jadwal_id  [description]
     * @param  [type] $peserta_id [description]
     * @return [type]             [description]
     */
    public static function getJawabanPeserta($jadwal_id, $peserta_id, $acak_opsi)
    {
        $find = JawabanPeserta::with([
          'soal' => function($q) {
            $q->select('id','banksoal_id','pertanyaan','tipe_soal','audio','direction');
        },'soal.jawabans' => function($q) use ($acak_opsi) {
            $q->select('id','soal_id','text_jawaban');
            if($acak_opsi == "1") {
                $q->inRandomOrder();
            }
        }
        ])->where([
            'peserta_id'    => $peserta_id,
            'jadwal_id'     => $jadwal_id,
        ])
        ->select('id','banksoal_id','soal_id','jawab','esay','jawab_complex','ragu_ragu')
        ->get()
        ->makeHidden('similiar');
        $data = $find->map(function($item) {
            if ($item->soal->tipe_soal == 5) {
                $jwra = [];
                $jwrb = [];
                foreach($item->soal->jawabans as $key => $jwb) {
                    $jwb_arr = json_decode($jwb->text_jawaban, true);
                    array_push($jwra, [
                        'id' => $jwb_arr['a']['id'],
                        'text' => $jwb_arr['a']['text'],
                    ]);
                    array_push($jwrb, [
                        'id' => $jwb_arr['b']['id'],
                        'text' => $jwb_arr['b']['text'],
                    ]);
                }

                $jwra = Arr::shuffle($jwra);
                $jwrb = Arr::shuffle($jwrb);
            }


            $jawabans = [];
            if (in_array($item->soal->tipe_soal, [1,2,3,4,5])) {
                $jawabans = in_array($item->soal->tipe_soal, [1,2,3,4])
                    ? $item->soal->jawabans
                    : $item->soal->jawabans->map(function($jw, $index) use ($jwra, $jwrb){
                    return [
                        'a' => $jwra[$index],
                        'b' => $jwrb[$index],
                    ];
                });
            }

            return [
                'id'    => $item->id,
                'banksoal_id' => $item->banksoal_id,
                'soal_id' => $item->soal_id,
                'jawab' => $item->jawab,
                'esay' => $item->esay,
                'jawab_complex' => $item->jawab_complex,
                'soal' => [
                    'audio' => $item->soal->audio,
                    'banksoal_id' => $item->soal->banksoal_id,
                    'direction' => $item->soal->direction,
                    'id' => $item->soal->id,
                    'jawabans' => $jawabans,
                    'pertanyaan' => $item->soal->pertanyaan,
                    'tipe_soal' => $item->soal->tipe_soal,
                ],
                'ragu_ragu' => $item->ragu_ragu,
            ];
        });
        return $data;
    }

    /**
     * [finishingUjian description]
     * @param  [type] $jadwal_id  [description]
     * @param  [type] $peserta_id [description]
     * @return [type]             [description]
     */
    public static function finishingUjian($banksoal_id, $jadwal_id, $peserta_id)
    {
        $banksoal = Banksoal::find($banksoal_id);
        if(!$banksoal) {
            return ['success' => false, 'message' => 'Tidak dapat menemukan banksoal'];
        }
        try {
            // Pilihan Ganda
            $hasil_pg = 0;
            $pg_benar = 0;
            $pg_salah = 0;
            if($banksoal->jumlah_soal > 0) {
                $pg_benar = JawabanPeserta::where([
                    'iscorrect'     => 1,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '1');
                })
                ->count();
                $pg_salah = JawabanPeserta::where([
                    'iscorrect'     => 0,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id,
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','1');
                })
                ->count();

                $pg_jml = JawabanPeserta::where([
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '1');
                })
                ->count();

                if($pg_jml > 0 && $pg_benar > 0) {
                    $hasil_pg = ($pg_benar/$pg_jml)*$banksoal->persen['pilihan_ganda'];
                }
            }

            // Pilihan Ganda Komplek
            $hasil_mpg = 0;
            $mpg_salah = 0;
            $mpg_benar = 0;
            if($banksoal->jumlah_soal_ganda_kompleks > 0) {
                $mpg_benar = JawabanPeserta::where([
                    'iscorrect'     => 1,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal', '=', '4');
                })
                ->count();

                $mpg_salah = JawabanPeserta::where([
                    'iscorrect'     => 0,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal', '=', '4');
                })
                ->count();

                $mpg_jml = JawabanPeserta::where([
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '4');
                })
                ->count();

                if($mpg_jml > 0 && $mpg_benar > 0) {
                    $hasil_mpg = ($mpg_benar/$mpg_jml)*$banksoal->persen['pilihan_ganda_komplek'];
                }
            }

            // Listening
            $hasil_listening = 0;
            $listening_benar = 0;
            $listening_salah = 0;
            if($banksoal->jumlah_soal_listening > 0) {
                $listening_benar = JawabanPeserta::where([
                    'iscorrect'     => 1,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '3');
                })
                ->count();
                $listening_salah = JawabanPeserta::where([
                    'iscorrect'     => 0,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id,
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','3');
                })
                ->count();

                $listening_jml = JawabanPeserta::where([
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '3');
                })
                ->count();

                if($listening_jml > 0 && $listening_benar > 0) {
                    $hasil_listening = ($listening_benar/$listening_jml)*$banksoal->persen['listening'];
                }
            }

            // Isian singkat
            $hasil_isiang_singkat = 0;
            $isian_singkat_benar = 0;
            $isian_singkat_salah = 0;
            if($banksoal->jumlah_isian_singkat > 0) {
                $isian_singkat_benar = JawabanPeserta::where([
                    'iscorrect'     => 1,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '6');
                })
                ->count();
                $isian_singkat_salah = JawabanPeserta::where([
                    'iscorrect'     => 0,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id,
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','6');
                })
                ->count();

                $isiang_singkat_jml = JawabanPeserta::where([
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '6');
                })
                ->count();

                if($isiang_singkat_jml > 0 && $isian_singkat_benar > 0) {
                    $hasil_isiang_singkat = ($isian_singkat_benar/$isiang_singkat_jml)*$banksoal->persen['isian_singkat'];
                }
            }

            // Menjodohkan
            $hasil_menjodohkan = 0;
            $jumlah_menjodohkan_benar = 0;
            $jumlah_menjodohkan_salah = 0;
            if($banksoal->jumlah_menjodohkan > 0) {
                $jumlah_menjodohkan_benar = JawabanPeserta::where([
                    'iscorrect'     => 1,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '5');
                })
                ->count();
                $jumlah_menjodohkan_salah = JawabanPeserta::where([
                    'iscorrect'     => 0,
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id,
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','5');
                })
                ->count();

                $jumlah_menjodohkan_jml = JawabanPeserta::where([
                    'jadwal_id'     => $jadwal_id,
                    'peserta_id'    => $peserta_id
                ])
                ->whereHas('soal', function($query) {
                    $query->where('tipe_soal','=', '5');
                })
                ->count();

                if($jumlah_menjodohkan_jml > 0 && $jumlah_menjodohkan_benar > 0) {
                    $hasil_isiang_singkat = ($jumlah_menjodohkan_benar/$jumlah_menjodohkan_jml)*$banksoal->persen['menjodohkan'];
                }
            }

            // Resulting Score
            $null = JawabanPeserta::where([
                'jawab'         => 0,
                'jadwal_id'     => $jadwal_id,
                'peserta_id'    => $peserta_id,
            ])
            ->whereHas('soal', function($query) {
                $query->whereIn('tipe_soal',['1','3']);
            })
            ->count();

            $hasil = $hasil_pg+$hasil_listening+$hasil_mpg+$hasil_isiang_singkat+$hasil_menjodohkan;

            HasilUjian::create([
                'banksoal_id'                   => $banksoal_id,
                'peserta_id'                    => $peserta_id,
                'jadwal_id'                     => $jadwal_id,
                'jumlah_salah'                  => $pg_salah,
                'jumlah_benar'                  => $pg_benar,
                'jumlah_benar_complek'          => $mpg_benar,
                'jumlah_salah_complek'          => $mpg_salah,
                'jumlah_benar_listening'        => $listening_benar,
                'jumlah_salah_listening'        => $listening_salah,
                'jumlah_benar_isian_singkat'    => $isian_singkat_benar,
                'jumlah_salah_isian_singkat'    => $isian_singkat_salah,
                'jumlah_benar_menjodohkan'      => $jumlah_menjodohkan_benar,
                'jumlah_salah_menjodohkan'      => $jumlah_menjodohkan_salah,
                'tidak_diisi'                   => $null,
                'hasil'                         => $hasil,
                'point_esay'                    => 0
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        return ['success' => true, 'message' => 'success to store ujian siswa'];
    }

    /**
     * [kurangiSisaWaktu description]
     * @param  SiswaUjian $siswaUjian [description]
     * @return [type]                 [description]
     */
    public static function kurangiSisaWaktu(SiswaUjian $siswaUjian)
    {
        $deUjian = Jadwal::find($siswaUjian->jadwal_id);
        $start = Carbon::createFromFormat('H:i:s', $siswaUjian->mulai_ujian);
        $now = Carbon::createFromFormat('H:i:s', Carbon::now()->format('H:i:s'));
        $diff_in_minutes = $start->diffInSeconds($now);
        $siswaUjian->sisa_waktu = $deUjian->lama-$diff_in_minutes;
        $siswaUjian->save();
    }

    /**
     * [getUjianSiswaBelumSelesai description]
     * @param  SiswaUjian $siswaUjian [description]
     * @return [type]                 [description]
     */
    public function getUjianSiswaBelumSelesai($peserta_id)
    {
        $data = SiswaUjian::where(function($query) use($peserta_id) {
            $query->where('peserta_id', $peserta_id)
            ->where('status_ujian','=',3);
        })->first();

        return $data;
    }
}
