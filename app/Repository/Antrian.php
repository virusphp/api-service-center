<?php

namespace App\Repository;

use App\Helpers\BPJSHelper;
use App\Helpers\Waktu;
use App\Service\Bpjs\Bridging;
use DB;
use Carbon\Carbon;
use Exception;

class Antrian
{
    protected $dbsimrs = "sql_simrs";

    public function postAntrian($params)
    {
        try {
            $saveRegister = $this->simpanRegistrasi($params);
            if ($saveRegister) {
                return $saveRegister;
            }
            return $saveRegister;
        } catch (Exception $e) {
            $e->getMessage();
        }

    }

    // --- TABEL REGISTRASI
    private function simpanRegistrasi($params)
    {
        $dataPasien = $this->getDataPasien($params->nomorkartu);
        // dd($dataPasien);
        if ($dataPasien == null) {
            $res['code']  = 201;
            $res['pesan'] = "Pasien tersebut belom terdaftar di rumah sakit kami!";
            return $res;
        }
        $noReg             = $this->generateNomor($params->tanggalperiksa);
        $dataPasien->waktu = date('H:i:s');
        $asalPasien        = $this->asalPasien($params->jenisreferensi, $params->nomorreferensi);
        $statusPengunjung   = $this->getStatusPengunjung($dataPasien->no_rm);
        $kodePenjamin      = $this->getKodePenjamin($params->nomorkartu, $params->tanggalperiksa);
        // dd($noReg, $dataPasien->no_rm, $params->tanggalperiksa, $dataPasien->waktu, $asalPasien, $statusPengunjung, $kodePenjamin);

        $saveRegister = DB::connection($this->dbsimrs)->table('registrasi')->insert([
            'no_reg' => $noReg,
            'no_RM' => $dataPasien->no_rm,
            'tgl_reg' => $params->tanggalperiksa,
            'waktu' => $dataPasien->waktu,
            'kd_asal_pasien' => $asalPasien,
            'status_pengunjung' => $statusPengunjung,
            'kd_cara_bayar' => 8,
            'jenis_pasien' => 0,
            'no_reg_pembayar' => $noReg,
            'kd_penjamin' => $kodePenjamin,
            'no_SJP' => '-',
            'user_id' => '0000000'
        ]);

        if ($saveRegister) {
            $rajal = $this->saveRajal($noReg, $params, $statusPengunjung, $dataPasien);

            if ($rajal['code'] == 200) {
                $res['code'] = 200;
                $res['nomorantrean'] = "";
                $res['kodebooking'] = "";
                $res['jenisantrean'] = 2;
                $res['estimasidilayani'] = 212381923;
                $res['namapoli'] = "POLI DALAM";
                $res['namadokter'] = "dr. HISYAM";
                return $res;
            }
        
        }

        // // ---- TABEL RAWAT JALAN
        // $data->kd_sub_unit = $dataPoli->kd_sub_unit;
        // $data->kd_cara_kunjungan = 1;
        // $data->status_kunjungan  = 1;
        // $data->waktu_anamnesa    = date('H:i:s');
        // $data->kd_dokter  = "KODE DOKTER"; 
        // $data->reg_sms  = 3;

        // $data->no_rujukan =  $params->jenisreferensi;
        // $data->jenis_rujukan =  $params->jenisreferensi;
        // $data->poli_eksekutif =  $params->polieksekutif;
        // $data->nik = $params->nik;
        // $data->no_telp = $params->notelp;
        // dd($data);
    }

    private function saveRajal($noReg, $params, $statusKunjungan, $dataPasien)
    {
        $dokterPoli = $this->getDokterPoli($params->kodepoli, $params->tanggalperiksa);
        $waktuAnamnesa = date("Y-m-d h:i:s");
        
        if (empty($dokterPoli)) {
            $res['code'] = 201;
            $res['message'] = "Dokter tujuan libur / Poli terpilih tutup!";
            return $res;
        }

        $rawatJalan = DB::connection($this->dbsimrs)->table('rawat_jalan')->insert([
            'no_reg' => $noReg,
            'no_rm' => $dataPasien->no_rm,
            'kd_poliklinik' => $dokterPoli->kd_sub_unit,
            'kd_cara_kunjungan' => 1,
            'status_kunjungan' => $statusKunjungan,
            'waktu_anamnesa' => $waktuAnamnesa,
            'kd_dokter' => $dokterPoli->kd_pegawai,
            'reg_sms' => 3
        ]);

        if (!$rawatJalan) {
            $res['code'] = 201;
            $res['message'] = "Registrasi poli gagal terjadi kesalahan sistem";
            return $res;
        }
        $res['code'] = 200;
        $res['message'] = "Registrasi Rajat Jalan Sukses!";
        return $res;
    }

    private function getKodePenjamin($nomorKartu, $tanggal)
    {
        $serviceBPjS = new Bridging(config('bpjs.api.consid'), BPJSHelper::timestamp(), BPJSHelper::signature(config('bpjs.api.consid'), config('bpjs.api.seckey')));
        $endpoint = 'Peserta/nokartu/'. $nomorKartu . "/tglSEP/" . $tanggal;
        $result = json_decode($serviceBPjS->getRequest($endpoint));
        if ($result->response != null) {
            $jenisPeserta = $result->response->peserta->jenisPeserta->keterangan;
            $pecah = explode(" ", $jenisPeserta);
            $search = array_search("PBI", $pecah);
            if ($search === false) {
                $kodePenjamin = 24;
            } else {
                $kodePenjamin = 23;
            }
            $kodePenjamin = $kodePenjamin;
        }
        return $kodePenjamin;
    }

    private function generateNomor($tglReg)
    {
        $dataReg = "01". date('dmy', strtotime($tglReg));
        $noReg = DB::connection($this->dbsimrs)->table('registrasi')
                    ->where('no_reg', 'like', $dataReg . '%')
                    ->max('no_reg');
        $noUrut = (int) substr($noReg, -4);
        $noUrut++;
        $newCode = $dataReg . sprintf("%04s", $noUrut);
        return $newCode;
    }

    private function asalFaskes($kodeFaskes)
    {
        $kode = substr($kodeFaskes, 0, 8);
        return DB::connection($this->dbsimrs)->table('faskes_bpjs')->select('jenis_faskes')
                ->where('kode', $kode)->first();
    }

    private function asalPasien($jenisRujukan, $noRujukan)
    {
        if ($jenisRujukan == 1)
        {
            $faskes = $this->asalFaskes($noRujukan); 
            if ($faskes) {
                $asalPasien = (int) $faskes->jenis_faskes;
            } else {
                $asalPasien = 0;
            }
        } else {
            $asalPasien = 0;
        }

        return $asalPasien;
    }

    private function getDataPasien($nomorKartu)
    {
        return DB::connection($this->dbsimrs)->table('penjamin_pasien')->select('no_rm','no_kartu')
                ->where('no_kartu', $nomorKartu)->first();
    }

    private function getDokterPoli($kodePoli, $tanggal)
    {
        return DB::connection($this->dbsimrs)->table('jadwal_dokter_poli_rj as j')->select('j.kd_sub_unit','j.kd_pegawai')
                ->join('sub_unit as s', 'j.kd_sub_unit', '=', 's.kd_sub_unit')
                ->where([
                    ['s.kd_poli_dpjp', $kodePoli],
                    ['j.kd_hari', Waktu::tanggalToNilai($tanggal)]
                ])
                ->first();
    }

    private function getStatusPengunjung($noRm)
    {
        $data = DB::connection($this->dbsimrs)->table('registrasi')->select('no_rm')
                ->where('no_rm', $noRm)->get();
        
        if ($data->count() != 0) {
            $statusPenunjung = 0;
        } else {
            $statusPenunjung = 1;
        }

        return $statusPenunjung;
    }
}