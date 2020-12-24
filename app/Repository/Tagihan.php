<?php

namespace App\Repository;
use App\Transform\TransformTagihan;
use App\Helpers\Waktu;
use DB;
ini_set('max_input_vars',5000);
ini_set('max_input_nesting_level',5000);

class Tagihan
{
    protected $dbsimrs = "sql_simrs";

    public function __construct()
    {
        $this->transform = new TransformTagihan;
    }

    public function getPasien($params)
    {
        return DB::connection($this->dbsimrs)->table('pasien as p')
            ->select('p.no_rm','p.nama_pasien','p.alamat','p.rt','p.rw', 'p.jns_kel','p.tgl_lahir','kel.nama_kelurahan','kec.nama_kecamatan','kab.nama_kabupaten','prov.nama_propinsi')
            ->join('kelurahan as kel','p.kd_kelurahan','=','kel.kd_kelurahan','left')
            ->join('kecamatan as kec','kel.kd_kecamatan','=','kec.kd_kecamatan')
            ->join('kabupaten as kab','kec.kd_kabupaten','=','kab.kd_kabupaten')
            ->join('propinsi as prov','kab.kd_propinsi','=','prov.kd_propinsi')
            ->where('p.no_rm', $params['no_rm'])
            ->first();
    }   
    
    public function getnoReg($params)
    {   
        return DB::connection($this->dbsimrs)->table('registrasi')
            ->select('no_reg')
            ->where([
                ['no_rm', $params['no_rm']],
                ['tgl_reg', $params['tanggal_registrasi']],
            ])
            ->get();
    }

    public function getnoRegPembayar($params)
    {   
        return DB::connection($this->dbsimrs)->table('registrasi')
            ->select('no_reg_pembayar')
            ->where([
                ['no_rm', $params['no_rm']],
                ['tgl_reg', $params['tanggal_registrasi']],
            ])
            ->groupBy('no_reg_pembayar')
            ->get();
    }

    public function getTagihanPasien($params)
    {                
        $dataReg = $this->getnoRegPembayar($params);
        if(!$dataReg->count()){
            return "01";
        }else{  
            foreach($dataReg as $key=>$value){
                $data [$key] = $value->no_reg_pembayar;
            }            
            $noRegPembayar = $data;
            return DB::connection($this->dbsimrs)->table('view_kwitansi as a')
                ->select(DB::raw("CASE a.Status_Verifikasi WHEN 1 THEN 'SUDAH' ELSE 'BELUM' END as status_verifikasi"),'a.no_bukti','a.tgl_tagihan','a.kelompok','a.nama_tarif','a.harga','a.biaya_jaminan','a.jumlah','a.diskon_rupiah','a.tunai','a.piutang','a.tagihan','a.rek_p','a.rek_p2','a.no_tagihan','a.no_rm','a.no_reg','r.no_reg_pembayar','a.diskon_persen','a.kd_sub_unit','a.posisi','a.kd_dokter','a.Tagihan_A')
                ->join('registrasi as r','a.no_reg','=','r.no_reg')
                ->whereIn('r.no_reg_pembayar',$noRegPembayar)
                ->get();  
        }
    }

    public function bayarTagihan($params)
    {
        $setdata = ($params->all());
        $getTagihan = $this->getTagihanPasien($setdata);
        if($getTagihan=='01'){
            $response= [
                'status' => '01'
            ];
            return $response;
        }else if(!$getTagihan->count()){
            // dd('wes dibayar gan');
            $dataRegBayar = $this->getnoRegPembayar($setdata);
            foreach($dataRegBayar as $key=>$value){
                $data [$key] = $value->no_reg_pembayar;
            }   
            $noRegPembayar = $data;
            $response= [
                'status' => '02',
                'data' => $this->cariKwitansiByNoreg($noRegPembayar)
            ];
            return $response;
         }else{                                      
            return $this->InsertKwitansi($setdata,$getTagihan);
         }
    }

    public function cariKwitansiByNoreg($params)
    {
        $getKw = DB::connection($this->dbsimrs)->table('Kwitansi_Header')
                ->select('no_kwitansi','nama_pembayar','alamat_pembayar','untuk','tgl_kwitansi','jenis_rawat','no_rm','no_reg','nama_pasien','alamat','jenis_pasien','nama_penjamin','grandtotal_tunai as tunai')
                ->whereIn('no_reg',$params)
                ->get();
        return $getKw;
    }

    public function InsertKwitansi($data,$getTagihan)
    {
        $pasien = $this->getPasien($data);
        // dd($getTagihan);
        $transform = $this->transform->mapperTagihanBayar($pasien, $getTagihan, $data);
        $response= [
            'status' => '00',
            'data' => $this->InsertKwitansiHeader($transform)
        ];
        return $response;
    }

    // public function getnoReg($params,$jnsrawat)
    // {   
    //     $cariReg = DB::connection($this->dbsimrs)->table('registrasi')
    //             ->select('no_reg','jns_rawat','status_keluar')
    //             ->where([
    //                 ['no_rm', $params['no_rm']],
    //                 ['tgl_reg', $params['tanggal_registrasi']],
    //                 ['jns_rawat',$jnsrawat]
    //             ])
    //             ->orderBy('status_keluar', 'DESC')
    //             ->get();
    //     return $cariReg;
    // }

    // public function getTagihanRJ($params)
    // {                
    //     $dataReg = $this->getnoReg($params,1);
    //     if(!$dataReg->count()){
    //         return "01";
    //     }else{  
    //         foreach($dataReg as $key=>$value){
    //             $data [$key] = $value->no_reg;
    //             // $data2 [$key] = $value->status_keluar;
    //         }            
    //         $noReg = $data;
    //         return DB::connection($this->dbsimrs)->table('view_kwitansi as k')
    //             ->select('k.no_tagihan','k.Tagihan_A','k.kelompok','k.no_bukti','k.no_reg','k.tgl_tagihan','k.nama_tarif','k.kelompok','k.harga','k.tunai','k.piutang','k.tagihan','k.posisi','k.rek_p','k.rek_p2','k.jumlah','k.kd_dokter','k.kd_sub_unit')
    //             ->where('k.no_rm', $params['no_rm'])
    //             ->whereIn('k.no_reg',$noReg)
    //             ->where('k.no_reg','like','01%')
    //             ->get();
                       
    //     }
    // }

    // public function getTagihanRI($params)
    // {   
    //     $dataReg = $this->getnoReg($params,2);
    //     if(!$dataReg->count()){
    //         return "01";
    //     }else{  
    //         foreach($dataReg as $key=>$value){
    //             $data [$key] = $value->no_reg;
    //             // $data2 [$key] = $value->status_keluar;
    //         }            
    //         $noReg = $data;
    //         return DB::connection($this->dbsimrs)->table('view_kwitansi as k')
    //             ->select('k.no_tagihan','k.Tagihan_A','k.kelompok','k.no_bukti','k.no_reg','k.tgl_tagihan','k.nama_tarif','k.kelompok','k.harga','k.tunai','k.piutang','k.tagihan','k.posisi','k.rek_p','k.rek_p2','k.jumlah','k.kd_dokter','k.kd_sub_unit')
    //             ->where('k.no_rm', $params['no_rm'])
    //             ->whereIn('k.no_reg',$noReg)
    //             ->where('k.no_reg','like','02%')
    //             ->get();
    //     }
    // }

    // public function getTagihanRD($params)
    // {
    //     $dataReg = $this->getnoReg($params,3);
    //     if(!$dataReg->count()){
    //         return "01";
    //     }else{  
    //         foreach($dataReg as $key=>$value){
    //             $data [$key] = $value->no_reg;
    //             // $data2 [$key] = $value->status_keluar;
    //         }            
    //         $noReg = $data;
    //         return DB::connection($this->dbsimrs)->table('view_kwitansi as k')
    //             ->select('k.no_tagihan','k.Tagihan_A','k.kelompok','k.no_bukti','k.no_reg','k.tgl_tagihan','k.nama_tarif','k.kelompok','k.harga','k.tunai','k.piutang','k.tagihan','k.posisi','k.rek_p','k.rek_p2','k.jumlah','k.kd_dokter','k.kd_sub_unit')
    //             ->where('k.no_rm', $params['no_rm'])
    //             ->whereIn('k.no_reg',$noReg)
    //             ->where('k.no_reg','like','03%')
    //             ->get();
    //     }
    // }
   
    public function InsertKwitansiHeader($data)
    {  
        // dd($data);
        DB::beginTransaction();
        try {  
            // $dataKwHeader=[];
            // $dataKw=[];
            foreach($data['tagihan_total'] as $key=>$val){ 
                $no_kw[]= $this->no_kwitansi($val['jenis_rawat'],$val['int']);               
                $nokwitansi= $this->no_kwitansi($val['jenis_rawat'],$val['int']);
                $dataKwHeader= array(
                    'no_kwitansi' => $nokwitansi,
                    'nama_pembayar' =>$data['pasien']['nama_pembayar'],
                    'alamat_pembayar' =>$data['pasien']['alamat_pembayar'],
                    'untuk' =>'Pembayaran Biaya Tagihan Rumah Sakit',
                    'tgl_kwitansi' =>date('Y-m-d'),
                    'jenis_rawat' =>$val['jenis_rawat'],
                    'no_rm' =>$data['pasien']['no_rm'],
                    'no_reg' =>$val['no_reg'],
                    'nama_pasien' =>$data['pasien']['nama_pasien'],
                    'alamat' =>$data['pasien']['alamat_pasien'],
                    'jenis_pasien' =>'UMUM',
                    'kd_penjamin' =>'',
                    'nama_penjamin' =>'-',
                    'no_surat' =>'-',
                    'uang_muka' =>0,
                    'tunai' =>$val['total_tunai'],
                    'piutang' =>$val['total_piutang'],
                    'tagihan' =>$val['total_tagihan'],
                    'retur_obat_tunai' =>$val['retur_obat'],
                    'retur_obat_piutang' =>'',
                    'potongan' =>'',
                    'grandtotal_tunai' =>$val['total_bayar'],
                    'grandtotal_piutang' =>$val['total_piutang'],
                    'grandtotal_Tunai_Bulet' =>$val['total_bayar'],
                    'Iur_Bayar' =>'',
                    'Iur_Bayar_Bulet' =>'',
                    'kd_kasir' =>'00',
                    'status_bayar' =>'LUNAS',
                    'user_id' =>'00000000',
                    'tgl_insert' =>Waktu::tanggalInsert(),
                    'Potongan_Persen_Obat' => '',
                    'Potongan_Rupiah_Obat' => '',
                    'Potongan_Persen_Tindakan' => '',
                    'Potongan_Rupiah_Tindakan' => '',
                    'Paket_Hak_Kelas' =>0,
                    'Paket_Kelas_Rawat' =>0,
                    'metode_pembayaran' => 2
                );  

                foreach($data['tagihan_detail'][$key] as $r){
                    $dataKw= array(
                        "no_kwitansi"=>$nokwitansi, 
                        "jenis_rawat"=>$r['jenis_rawat'], 
                        "tgl_kwitansi"=>date('Y-m-d'), 
                        "no_bukti"=>$r['no_bukti'], 
                        "no_rm"=>$data['pasien']['no_rm'], 
                        "no_reg"=>$r['no_reg'], 
                        "tgl_tagihan"=>$r['tanggal_tagihan'], 
                        "nama_tarif"=>$r['nama_tarif'], 
                        "kelompok"=>$r['kelompok'], 
                        "harga"=>$r['harga'], 
                        "biaya_jaminan"=>0, 
                        "jumlah"=>$r['jumlah'], 
                        "tunai"=>$r['tunai'], 
                        "tagihan"=>$r['tagihan'], 
                        "kd_dokter"=>$r['kd_dokter'], 
                        "penjamin"=>'-', 
                        "no_surat"=>'-', 
                        "metode_bayar"=>'TUNAI', 
                        "status_bayar"=>'LUNAS', 
                        "user_id"=>'bankjateng', 
                        "tgl_insert"=> Waktu::tanggalInsert(), 
                        "kd_kasir"=>'00', 
                        "kd_sub_unit"=>$r['kd_subunit'],
                        "Rek_P"=>$r['akun_rek1'], 
                        "Rek_P2"=>$r['akun_rek2'],
                    );

                    $update[]= [
                        'no_rm'=> $data['pasien']['no_rm'],
                        'no_reg' => $r['no_reg'],
                        'no_tagihan' => $r['no_tagihan'],
                        'no_bukti' => $r['no_bukti'],
                        'kelompok_tagihan' => $r['kelompok_tagihan'],
                        'no_kwitansi' =>$nokwitansi
                    ];  
                    // dd($dataKw); 
                    DB::connection($this->dbsimrs)->table('Kwitansi')->insert($dataKw); 
                }  
                $updateReg[]=[
                    'no_reg_pembayar' =>$val['no_reg']
                ];
                DB::connection($this->dbsimrs)->table('Kwitansi_Header')->insert($dataKwHeader); 
                // dd($dataKw);
            }  
            // dd($dataKw);
            // $query = DB::connection($this->dbsimrs)->table('Kwitansi_Header')->insert($dataKwHeader);  
            // $query = DB::connection($this->dbsimrs)->table('Kwitansi')->insert($dataKw); 
            // dd($query);
            $this->deleteTotalTagihanPasien($update);           
            $this->updateTagihanPasien($update);            
            $query = $this->updateRegistrasi($updateReg); 
            DB::commit();
            if($query){                
                return $this->getKwitansiBayar($no_kw);
            }
            else{
                DB::rollback();
                return false;
            }           
        }
        catch(Exception $e) {
            DB::rollback();
            return $e->getMessage();
        } 
    }

    public function getKwitansiBayar($no_kw)
    {        
        $getKw = DB::connection($this->dbsimrs)->table('Kwitansi_Header')
                ->select('no_kwitansi','nama_pembayar','alamat_pembayar','untuk','tgl_kwitansi','jenis_rawat','no_rm','no_reg','nama_pasien','alamat','jenis_pasien','nama_penjamin','grandtotal_tunai as tunai')
                ->whereIn('no_kwitansi',$no_kw)
                ->get();
        return $getKw;
    }

    public function no_kwitansi($jns_rawat,$key)
    {
        $jenis =  "K".$jns_rawat.date('dmy');       
        $query = DB::connection($this->dbsimrs)->table('kwitansi_header')
                ->where('no_kwitansi','like',$jenis.'%')
                ->max('no_kwitansi');
        $noUrut = (int) substr($query, 9, 4);
        $noUrut++;
        if($key > 0){
            $noUrutBaru = $noUrut+$key;
        }else{
            $noUrutBaru = $noUrut;
        } 
        $newID = $jenis . sprintf("%04s", $noUrutBaru).'00';
        return $newID; 
    }

    public function updateRegistrasi($value)
    {    
        return DB::connection($this->dbsimrs)->table('Registrasi')
            ->whereIn('no_reg_pembayar',$value)
            ->update(['Status_keluar'=>1]);
    }

    public function deleteTotalTagihanPasien($value)
    {
        foreach($value as $val){
            return DB::connection($this->dbsimrs)->table('total_tagihan_pasien')
            ->where([
                ['no_rm',$val['no_rm']],
                ['no_reg',$val['no_reg']],
                ['kode',$val['no_tagihan']]
            ])
            ->delete();
        }     
    }

    public function updateTagihanPasien($value)
    {
        DB::beginTransaction();
        try {
            foreach($value as $val){
                $kelompok = $val['kelompok_tagihan'];
                if($kelompok=='TAGIHAN_PASIEN'){      
                    DB::connection($this->dbsimrs)->table('tagihan_pasien')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_tagihan',$val['no_tagihan']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar' =>'SUDAH','status_verifikasi' =>1]);
                }
                else if($kelompok=='TAGIHAN_BA'){
                    DB::connection($this->dbsimrs)->table('tagihan_ba')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_tagihan',$val['no_tagihan']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar' =>'SUDAH','status_verifikasi' =>1]);
                }
                else if($kelompok=='TAGIHAN_FARMASI'){
                    DB::connection($this->dbsimrs)->table('resep_jual')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_nota',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH']);

                    DB::connection($this->dbsimrs)->table('resep_jual_detail')
                    ->where([
                        ['IDX',$val['no_tagihan']],                    
                        ['no_nota',$val['no_bukti']],
                        ['status_paket',0],
                    ])
                    ->update(['status_verifikasi'=>'1']);

                    DB::connection($this->dbsimrs)->table('ap_jualr1')
                    ->where([                                       
                        ['notaresep',$val['no_bukti']]
                    ])
                    ->update(['posting' =>'2']);
                }
                else if($kelompok=='TAGIHAN_KAMAR'){
                    DB::connection($this->dbsimrs)->table('Tagihan_Kamar')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH', 'Status_Verifikasi'=>'1']);
                }
                else if($kelompok=='TAGIHAN_FARMASI_PEGAWAI'){
                    DB::connection($this->dbsimrs)->table('Resep_Pegawai')
                    ->where([
                        ['kd_pegawai',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH']);

                    DB::connection($this->dbsimrs)->table('Resep_Pegawai_detail')
                    ->where([
                        ['IDX',$val['no_tagihan']],
                        ['status_paket',0],
                        ['no_nota',$val['no_bukti']]
                    ])
                    ->update(['Status_Verifikasi'=>'1']);
                }
                else if($kelompok=='TAGIHAN_FARMASI_BEBAS'){
                    DB::connection($this->dbsimrs)->table('Jual_Header')
                    ->where([
                        ['kd_pelanggan',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH']);

                    DB::connection($this->dbsimrs)->table('jual_detail')
                    ->where([
                        ['IDX',$val['no_tagihan']],
                        ['status_paket',0],
                        ['no_nota',$val['no_bukti']]
                    ])
                    ->update(['Status_Verifikasi'=>'1']);

                    DB::connection($this->dbsimrs)->table('ap_jualbbs1')
                    ->where([                                       
                        ['no_nota',$val['no_bukti']]
                    ])
                    ->update(['posting' =>'2']);
                }
                else if($kelompok=='TAGIHAN_FARMASI_RETUR_PASIEN'){
                    DB::connection($this->dbsimrs)->table('Resep_Jual_Retur')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_retur',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH']);

                    DB::connection($this->dbsimrs)->table('Resep_Jual_Detail_Retur')
                    ->where([
                        ['IDX',$val['no_tagihan']],
                        ['status_paket',0],
                        ['no_nota',$val['no_bukti']]
                    ])
                    ->update(['Status_Verifikasi'=>'1']);

                    DB::connection($this->dbsimrs)->table('ap_returinap1')
                    ->where([                                       
                        ['notaretur',$val['no_bukti']]
                    ])
                    ->update(['posting' =>'2']);
                }
                else if($kelompok=='TAGIHAN_FARMASI_RETUR_PEGAWAI'){
                    DB::connection($this->dbsimrs)->table('Resep_Pegawai_Retur')
                    ->where([
                        ['kd_pegawai',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']],
                        ['status_paket '=> 0]
                    ])
                    ->update(['status_bayar'=>'SUDAH']);

                    DB::connection($this->dbsimrs)->table('Resep_Pegawai_Detail_Retur')
                    ->where([
                        ['IDX',$val['no_tagihan']],
                        ['status_paket',0],
                        ['no_retur',$val['no_bukti']]
                    ])
                    ->update(['Status_Verifikasi'=>'1']);
                }
                else if($kelompok=='TAGIHAN_FARMASI_RETUR_BEBAS'){
                    DB::connection($this->dbsimrs)->table('Jual_Header_Retur')
                    ->where([
                        ['kd_pelanggan',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_retur',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH']);

                    DB::connection($this->dbsimrs)->table('Jual_Detail_Retur')
                    ->where([
                        ['IDX',$val['no_tagihan']],
                        ['status_paket',0],
                        ['no_retur',$val['no_bukti']]
                    ])
                    ->update(['Status_Verifikasi'=>'1']);

                    DB::connection($this->dbsimrs)->table('ap_jualbbs1')
                    ->where([                                       
                        ['no_nota',$val['no_bukti']]
                    ])
                    ->update(['posting' =>'2']);
                }
                else if($kelompok=='TAGIHAN_AMBULANCE'){
                    DB::connection($this->dbsimrs)->table('Tagihan_Ambulance')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH','Status_Verifikasi'=>1]);
                }
                else if($kelompok=='TAGIHAN_PENUNJANG_LUAR'){
                    DB::connection($this->dbsimrs)->table('Tagihan_Penunjang_Luar')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH','Status_Verifikasi'=>1]);
                }
                else if($kelompok=='PIUTANG_PENJAMIN_LAINNYA'){
                    DB::connection($this->dbsimrs)->table('Piutang_Penjamin_Lainnya')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH','Status_Verifikasi'=>1,'No_Kwitansi'=>$val['no_kwitansi']]);
                }
                else if($kelompok=='PIUTANG_PASIEN'){
                    DB::connection($this->dbsimrs)->table('Piutang_Pasien')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']],
                        ['no_bukti',$val['no_bukti']]
                    ])
                    ->update(['status_bayar'=>'SUDAH','Status_Verifikasi'=>1,'no_kwitansi'=>$val['no_kwitansi']]);
                }
                else if($kelompok=='UANG_MUKA'){
                    DB::connection($this->dbsimrs)->table('Piutang_Pasien')
                    ->where([
                        ['no_rm',$val['no_rm']],
                        ['no_reg',$val['no_reg']]
                    ])
                    ->update(['status_bayar'=>'SUDAH','Status_Verifikasi'=>1,'no_kwitansi'=>$val['no_kwitansi']]);
                }            
            }
            DB::commit();  
            return true;
        }
        catch(Exception $e) {
            DB::rollback();
            return $e->getMessage();
        } 

    }
   
}
