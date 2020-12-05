<?php

namespace Plugins\Ranap;

use Systems\AdminModule;

class Admin extends AdminModule
{
    public function navigation()
    {
        if ($this->core->getUserInfo('role') == 'admin') {
            return [
                'Manage' => 'manage',
                'Pengaturan' => 'settings'
            ];
        } else {
            return [
                'Manage' => 'manage'
            ];
        }
    }

    public function getManage()
    {

        $this->_addHeaderFiles();
        $bangsal = str_replace(",","','", $this->core->getUserInfo('cap', null, true));
        if ($this->core->getUserInfo('role') == 'admin' || $this->core->getUserInfo('role') == 'manajemen' || $this->core->getUserInfo('role') == 'rekammedis') {
          $bangsal = $this->db('bangsal')->select('kd_bangsal')->toArray();
          $bangsal = implode("','", array_map(function($obj) { foreach ($obj as $p => $v) { return $v;} }, $bangsal));
        }

        $query = $this->db()->pdo()
          ->prepare("SELECT reg_periksa.*,
              pasien.nm_pasien,
              pasien.alamat,
              kamar.kd_kamar,
              kamar_inap.tgl_masuk,
              kamar_inap.jam_masuk,
              dokter.nm_dokter,
              bangsal.nm_bangsal,
              penjab.png_jawab
            FROM reg_periksa, pasien, dokter, kamar_inap, kamar, bangsal, penjab
            WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis
            AND kamar_inap.no_rawat = reg_periksa.no_rawat
            AND kamar.kd_kamar = kamar_inap.kd_kamar
            AND bangsal.kd_bangsal = kamar.kd_bangsal
            AND reg_periksa.status_lanjut = 'Ranap'
            AND bangsal.kd_bangsal IN ('$bangsal')
            AND reg_periksa.kd_dokter = dokter.kd_dokter
            AND kamar_inap.stts_pulang = '-'
            AND reg_periksa.kd_pj = penjab.kd_pj");
        $query->execute();
        $rows = $query->fetchAll();

        $this->assign['list'] = [];
        foreach ($rows as &$row) {
            $row = htmlspecialchars_array($row);
            $row['editURL'] = url([ADMIN, 'ranap', 'edit', convertNorawat($row['no_rawat'])]);
            $row['viewURL'] = url([ADMIN, 'ranap', 'view', convertNorawat($row['no_rawat'])]);
            $row['dpjp'] = $this->db('dpjp_ranap')->select('nm_dokter')->join('dokter', 'dokter.kd_dokter = dpjp_ranap.kd_dokter')->where('no_rawat', $row['no_rawat'])->oneArray();
            $this->assign['list'][] = $row;
        }
        return $this->draw('manage.html', ['ranap' => $this->assign]);
    }

    public function getView($id, $page = 1)
    {
        $id = revertNorawat($id);
        $this->_addHeaderFiles();
        $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $id)->oneArray();
        $pasien = $this->db('pasien')->where('no_rkm_medis', $reg_periksa['no_rkm_medis'])->oneArray();
        $personal_pasien = $this->db('personal_pasien')->where('no_rkm_medis', $reg_periksa['no_rkm_medis'])->oneArray();
        $count_ralan = $this->db('reg_periksa')->where('no_rkm_medis', $reg_periksa['no_rkm_medis'])->where('status_lanjut', 'Ralan')->count();
        $count_ranap = $this->db('reg_periksa')->where('no_rkm_medis', $reg_periksa['no_rkm_medis'])->where('status_lanjut', 'Ranap')->count();
        $this->assign['print_rm'] = url([ADMIN, 'ranap', 'print_rm', $reg_periksa['no_rkm_medis']]);
        $this->assign['settings'] = $this->options('ranap');

        if (!empty($reg_periksa)) {
	          $perpage = '5';
            $this->assign['no_rawat'] = convertNorawat($id);
            $this->assign['view'] = $reg_periksa;
            $this->assign['view']['pasien'] = $pasien;
            $this->assign['view']['count_ralan'] = $count_ralan;
            $this->assign['view']['count_ranap'] = $count_ranap;
            $this->assign['soap'] = $this->db('pemeriksaan_ralan')->where('no_rawat', $id)->oneArray();
            $this->assign['rawat_dr'] = $this->db('rawat_jl_dr')->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')->where('no_rawat', $id)->toArray();
            $this->assign['rawat_pr'] = $this->db('rawat_jl_pr')->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')->where('no_rawat', $id)->toArray();
            $this->assign['rawat_drpr'] = $this->db('rawat_jl_drpr')->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')->where('no_rawat', $id)->toArray();
            if($reg_periksa['status_lanjut'] == 'Ranap') {
              $this->assign['soap'] = $this->db('pemeriksaan_ranap')->where('no_rawat', $id)->oneArray();
              $this->assign['rawat_dr'] = $this->db('rawat_inap_dr')->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_dr.kd_jenis_prw')->where('no_rawat', $id)->toArray();
              $this->assign['rawat_pr'] = $this->db('rawat_inap_pr')->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_pr.kd_jenis_prw')->where('no_rawat', $id)->toArray();
              $this->assign['rawat_drpr'] = $this->db('rawat_inap_drpr')->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_drpr.kd_jenis_prw')->where('no_rawat', $id)->toArray();
            }
            $this->assign['metode_racik'] = $this->core->db('metode_racik')->toArray();
            $this->assign['diagnosa_pasien'] = $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')->where('no_rawat', $id)->toArray();
            $this->assign['prosedur_pasien'] = $this->db('prosedur_pasien')->join('icd9', 'icd9.kode = prosedur_pasien.kode')->where('no_rawat', $id)->toArray();
            $this->assign['catatan'] = $this->db('catatan_perawatan')->where('no_rawat', $id)->oneArray();
            $this->assign['permintaan_resep'] = $this->db('resep_obat')
                ->join('resep_dokter', 'resep_dokter.no_resep = resep_obat.no_resep')
                ->join('databarang', 'databarang.kode_brng = resep_dokter.kode_brng')
                ->where('no_rawat', $id)
                ->toArray();
            $this->assign['permintaan_resep_racikan'] = $this->db('resep_obat')
                ->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep = resep_obat.no_resep')
                ->join('metode_racik', 'metode_racik.kd_racik = resep_dokter_racikan.kd_racik')
                ->join('resep_dokter_racikan_detail', 'resep_dokter_racikan_detail.no_resep = resep_dokter_racikan.no_resep')
                ->join('databarang', 'databarang.kode_brng = resep_dokter_racikan_detail.kode_brng')
                ->where('resep_obat.no_rawat', $id)
                ->group('resep_dokter_racikan.no_resep')
                ->select('resep_obat.no_resep')
                ->select('resep_dokter_racikan.nama_racik')
                ->select('metode_racik.nm_racik')
                ->select('resep_dokter_racikan.jml_dr')
                ->select('resep_dokter_racikan.aturan_pakai')
                ->select('resep_dokter_racikan.keterangan')
                ->select('group_concat(distinct concat(databarang.nama_brng, \'<br> - Kandungan: \', resep_dokter_racikan_detail.kandungan, \'<br> - Jumlah: \', resep_dokter_racikan_detail.jml) separator \'<br><br>\') AS detail_racikan')
                ->toArray();
            $this->assign['permintaan_lab'] = $this->db('permintaan_lab')
                ->join('permintaan_pemeriksaan_lab', 'permintaan_pemeriksaan_lab.noorder = permintaan_lab.noorder')
                ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw = permintaan_pemeriksaan_lab.kd_jenis_prw')
                ->where('no_rawat', $id)
                ->toArray();
            $this->assign['permintaan_rad'] = $this->db('permintaan_radiologi')
                ->join('permintaan_pemeriksaan_radiologi', 'permintaan_pemeriksaan_radiologi.noorder = permintaan_radiologi.noorder')
                ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw = permintaan_pemeriksaan_radiologi.kd_jenis_prw')
                ->where('no_rawat', $id)
                ->toArray();
            $this->assign['fotoURL'] = url(MODULES.'/ranap/img/'.$pasien['jk'].'.png');
            if(!empty($personal_pasien['gambar'])) {
              $this->assign['fotoURL'] = url(WEBAPPS_PATH.'/photopasien/'.$personal_pasien['gambar']);
            }
            $this->assign['master_berkas_digital'] = $this->db('master_berkas_digital')->toArray();
            $this->assign['berkas_digital'] = $this->db('berkas_digital_perawatan')->where('no_rawat', $id)->toArray();

            $this->assign['manageURL'] = url([ADMIN, 'ranap', 'manage']);
            $totalRecords = $this->db('reg_periksa')
                ->select('no_rawat')
                ->where('no_rkm_medis', $reg_periksa['no_rkm_medis'])
                ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
                ->desc('tgl_registrasi')
                ->toArray();
  	        $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), 10, url([ADMIN, 'ranap', 'view', convertNorawat($id), '%d']));
  	        $this->assign['pagination'] = $pagination->nav('pagination','5');
  	        $offset = $pagination->offset();
            $rows = $this->db('reg_periksa')
                ->where('no_rkm_medis', $reg_periksa['no_rkm_medis'])
                ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
                ->offset($offset)
                ->limit($perpage)
                ->desc('tgl_registrasi')
                ->toArray();

            foreach ($rows as &$row) {
                $pemeriksaan = $this->db('pemeriksaan_ralan')->where('no_rawat', $row['no_rawat'])->toArray();
                $rawat_dr = $this->db('rawat_jl_dr')->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')->where('no_rawat', $row['no_rawat'])->toArray();
                $rawat_pr = $this->db('rawat_jl_pr')->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')->where('no_rawat', $row['no_rawat'])->toArray();
                $rawat_drpr = $this->db('rawat_jl_drpr')->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')->where('no_rawat', $row['no_rawat'])->toArray();
                $row['nm_poli'] = $row['nm_poli'];
                if($row['status_lanjut'] == 'Ranap') {
                  $pemeriksaan = $this->db('pemeriksaan_ranap')->where('no_rawat', $row['no_rawat'])->toArray();
                  $rawat_dr = $this->db('rawat_inap_dr')->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_dr.kd_jenis_prw')->where('no_rawat', $row['no_rawat'])->toArray();
                  $rawat_pr = $this->db('rawat_inap_pr')->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_pr.kd_jenis_prw')->where('no_rawat', $row['no_rawat'])->toArray();
                  $rawat_drpr = $this->db('rawat_inap_drpr')->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_drpr.kd_jenis_prw')->where('no_rawat', $row['no_rawat'])->toArray();
                  $bangsal = $this->db('kamar_inap')
                    ->join('kamar', 'kamar.kd_kamar = kamar_inap.kd_kamar')
                    ->join('bangsal', 'bangsal.kd_bangsal = kamar.kd_bangsal')
                    ->where('no_rawat', $row['no_rawat'])
                    ->oneArray();
                  $row['nm_poli'] = $bangsal['nm_bangsal'].' - '.$bangsal['kd_kamar'];
                }
                $diagnosa_pasien = $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')->where('no_rawat', $row['no_rawat'])->toArray();
                $prosedur_pasien = $this->db('prosedur_pasien')->join('icd9', 'icd9.kode = prosedur_pasien.kode')->where('no_rawat', $row['no_rawat'])->toArray();
                $detail_pemberian_obat = $this->db('aturan_pakai')
                  ->join('databarang', 'databarang.kode_brng = aturan_pakai.kode_brng')
                  ->join('detail_pemberian_obat', 'detail_pemberian_obat.no_rawat = aturan_pakai.no_rawat')
                  //->join('resep_dokter', 'resep_dokter.no_resep = resep_obat.no_resep')
                  ->where('aturan_pakai.no_rawat', $row['no_rawat'])
                  //->where('resep_dokter.kode_brng', 'detail_pemberian_obat.kode_brng')
                  ->group('aturan_pakai.kode_brng')
                  //->select('databarang.nama_brng')
                  //->select('detail_pemberian_obat.jml')
                  //->select('resep_dokter.aturan_pakai')
                  ->toArray();
                $detail_periksa_lab = $this->db('detail_periksa_lab')->join('template_laboratorium', 'template_laboratorium.id_template = detail_periksa_lab.id_template')->where('no_rawat', $row['no_rawat'])->toArray();
                $hasil_radiologi = $this->db('hasil_radiologi')->where('no_rawat', $row['no_rawat'])->oneArray();
                $gambar_radiologi = $this->db('gambar_radiologi')->where('no_rawat', $row['no_rawat'])->toArray();
                $catatan_perawatan = $this->db('catatan_perawatan')->where('no_rawat', $row['no_rawat'])->oneArray();
                $berkas_digital = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->toArray();
                $row['reg_periksa'] = $reg_periksa;
                $row['keluhan'] = $pemeriksaan['keluhan'];
                $row['suhu_tubuh'] = $pemeriksaan['suhu_tubuh'];
                $row['tensi'] = $pemeriksaan['tensi'];
                $row['nadi'] = $pemeriksaan['nadi'];
                $row['respirasi'] = $pemeriksaan['respirasi'];
                $row['tinggi'] = $pemeriksaan['tinggi'];
                $row['berat'] = $pemeriksaan['berat'];
                $row['gcs'] = $pemeriksaan['gcs'];
                $row['pemeriksaan'] = $pemeriksaan['pemeriksaan'];
                $row['rtl'] = $pemeriksaan['rtl'];
                $row['catatan_perawatan'] = $catatan_perawatan['catatan'];
                $row['diagnosa_pasien'] = $diagnosa_pasien;
                $row['prosedur_pasien'] = $prosedur_pasien;
                $row['rawat_dr'] = $rawat_dr;
                $row['rawat_pr'] = $rawat_pr;
                $row['rawat_drpr'] = $rawat_drpr;
                $row['detail_pemberian_obat'] = $detail_pemberian_obat;
                $row['detail_periksa_lab'] = $detail_periksa_lab;
                $row['hasil_radiologi'] = str_replace("\n","<br>",$hasil_radiologi['hasil']);
                $row['gambar_radiologi'] = $gambar_radiologi;
                $row['berkas_digital'] = $berkas_digital;
                $this->assign['riwayat'][] = $row;
            }

            return $this->draw('view.html', ['ranap' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'ranap', 'manage']));
        }
    }

    public function postSOAPSave($id = null)
    {
        $errors = 0;
        $location = url([ADMIN, 'ranap', 'view', $id]);

        if (checkEmptyFields(['kd_dokter'], $_POST)) {
            $this->notify('failure', 'Nama dokter masih kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);

            $cek_no_rawat = $this->db('pemeriksaan_ranap')->where('no_rawat', revertNorawat($id))->oneArray();
            if(empty($cek_no_rawat['no_rawat'])) {
              $query = $this->db('pemeriksaan_ranap')
                ->save([
                  'no_rawat' => revertNorawat($id),
                  'tgl_perawatan' => date('Y-m-d'),
                  'jam_rawat' => date('H:i:s'),
                  'suhu_tubuh' => $_POST['suhu_tubuh'],
                  'tensi' => $_POST['tensi'],
                  'nadi' => $_POST['nadi'],
                  'respirasi' => $_POST['respirasi'],
                  'tinggi' => $_POST['tinggi'],
                  'berat' => $_POST['berat'],
                  'gcs' => $_POST['gcs'],
                  'keluhan' => $_POST['keluhan'],
                  'pemeriksaan' => $_POST['pemeriksaan'],
                  'alergi' => '-',
                  'rtl' => $_POST['rtl'],
                  'penilaian' => 'penilaiannya'
              ]);

              $get_kd_penyakit = $_POST['kd_penyakit'];
              for ($i = 0; $i < count($get_kd_penyakit); $i++) {
                $kd_penyakit = $get_kd_penyakit[$i];
                $query = $this->db('diagnosa_pasien')
                  ->save([
                    'no_rawat' => revertNorawat($id),
                    'kd_penyakit' => $kd_penyakit,
                    'status' => 'Ralan',
                    'prioritas' => $i+1,
                    'status_penyakit' => 'Lama'
                ]);
              }

              $get_kode = $_POST['kode'];
              for ($i = 0; $i < count($get_kode); $i++) {
                $kode = $get_kode[$i];
                $query = $this->db('prosedur_pasien')
                  ->save([
                    'no_rawat' => revertNorawat($id),
                    'kode' => $kode,
                    'status' => 'Ralan',
                    'prioritas' => $i+1
                ]);
              }

              $get_kd_jenis_prw = $_POST['kd_jenis_prw'];
              for ($i = 0; $i < count($get_kd_jenis_prw); $i++) {
                  $kd_jenis_prw = $get_kd_jenis_prw[$i];
                  $row = $this->db('jns_perawatan_inap')->where('kd_jenis_prw', $kd_jenis_prw)->oneArray();
                  $query = $this->db('rawat_inap_dr')
                    ->save([
                      'no_rawat' => revertNorawat($id),
                      'kd_jenis_prw' => $kd_jenis_prw,
                      'kd_dokter' => $_POST['kd_dokter'],
                      'tgl_perawatan' => date('Y-m-d'),
                      'jam_rawat' => date('H:i:s'),
                      'material' => $row['material'],
                      'bhp' => $row['bhp'],
                      'tarif_tindakandr' => $row['tarif_tindakandr'],
                      'kso' => $row['kso'],
                      'menejemen' => $row['menejemen'],
                      'biaya_rawat' => $row['total_byrdr']
                  ]);
              }

              $query = $this->db('catatan_perawatan')
                ->save([
                  'tanggal' => date('Y-m-d'),
                  'jam' => date('H:i:s'),
                  'no_rawat' => revertNorawat($id),
                  'kd_dokter' => $_POST['kd_dokter'],
                  'catatan' => $_POST['catatan']
              ]);

            } else {

              $query = $this->db('pemeriksaan_ranap')
                ->where('no_rawat', revertNorawat($id))
                ->update([
                  'suhu_tubuh' => $_POST['suhu_tubuh'],
                  'tensi' => $_POST['tensi'],
                  'nadi' => $_POST['nadi'],
                  'respirasi' => $_POST['respirasi'],
                  'tinggi' => $_POST['tinggi'],
                  'berat' => $_POST['berat'],
                  'gcs' => $_POST['gcs'],
                  'keluhan' => $_POST['keluhan'],
                  'pemeriksaan' => $_POST['pemeriksaan'],
                  'alergi' => '-',
                  'rtl' => $_POST['rtl'],
                  'penilaian' => 'penilaiannya'
              ]);

              $get_kd_penyakit = $_POST['kd_penyakit'];
              $this->db('diagnosa_pasien')->where('no_rawat', revertNorawat($id))->delete();
              for ($i = 0; $i < count($get_kd_penyakit); $i++) {
                $kd_penyakit = $get_kd_penyakit[$i];
                $query = $this->db('diagnosa_pasien')
                  ->save([
                    'no_rawat' => revertNorawat($id),
                    'kd_penyakit' => $kd_penyakit,
                    'status' => 'Ralan',
                    'prioritas' => $i+1,
                    'status_penyakit' => 'Lama'
                ]);
              }

              $get_kode = $_POST['kode'];
              $this->db('prosedur_pasien')->where('no_rawat', revertNorawat($id))->delete();
              for ($i = 0; $i < count($get_kode); $i++) {
                $kode = $get_kode[$i];
                $query = $this->db('prosedur_pasien')
                  ->save([
                    'no_rawat' => revertNorawat($id),
                    'kode' => $kode,
                    'status' => 'Ralan',
                    'prioritas' => $i+1
                ]);
              }

              $get_kd_jenis_prw = $_POST['kd_jenis_prw'];
              $this->db('rawat_inap_dr')->where('no_rawat', revertNorawat($id))->delete();
              for ($i = 0; $i < count($get_kd_jenis_prw); $i++) {
                  $kd_jenis_prw = $get_kd_jenis_prw[$i];
                  $row = $this->db('jns_perawatan_inap')->where('kd_jenis_prw', $kd_jenis_prw)->oneArray();
                  $query = $this->db('rawat_inap_dr')
                    ->save([
                      'no_rawat' => revertNorawat($id),
                      'kd_jenis_prw' => $kd_jenis_prw,
                      'kd_dokter' => $_POST['kd_dokter'],
                      'tgl_perawatan' => date('Y-m-d'),
                      'jam_rawat' => date('H:i:s'),
                      'material' => $row['material'],
                      'bhp' => $row['bhp'],
                      'tarif_tindakandr' => $row['tarif_tindakandr'],
                      'kso' => $row['kso'],
                      'menejemen' => $row['menejemen'],
                      'biaya_rawat' => $row['total_byrdr']
                  ]);
              }

              $query = $this->db('catatan_perawatan')
                ->where('no_rawat', revertNorawat($id))
                ->where('kd_dokter', $_POST['kd_dokter'])
                ->update([
                  'catatan' => $_POST['catatan']
              ]);

            }

            if ($query) {
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postRadiologiSave($id = null)
    {
        $errors = 0;
        $location = url([ADMIN, 'ranap', 'view', $id]);

        if (checkEmptyFields(['kd_dokter'], $_POST)) {
            $this->notify('failure', 'Nama dokter masih kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);
            $no_order = $this->core->setNoOrderRad();
            $query = $this->db('permintaan_radiologi')
              ->save([
                'noorder' => $no_order,
                'no_rawat' => revertNorawat($id),
                'tgl_permintaan' => date('Y-m-d'),
                'jam_permintaan' => date('H:i:s'),
                'tgl_sampel' => '0000-00-00',
                'jam_sampel' => '00:00:00',
                'tgl_hasil' => '0000-00-00',
                'jam_hasil' => '00:00:00',
                'dokter_perujuk' => $_POST['kd_dokter'],
                'status' => 'ranap',
                'informasi_tambahan' => $_POST['informasi_tambahan'],
                'diagnosa_klinis' => $_POST['diagnosa_klinis']
              ]);

            if ($query) {
                for ($i = 0; $i < count($_POST['kd_jenis_prw']); $i++) {
                  $query = $this->db('permintaan_pemeriksaan_radiologi')
                    ->save([
                      'noorder' => $no_order,
                      'kd_jenis_prw' => $_POST['kd_jenis_prw'][$i],
                      'stts_bayar' => 'Belum'
                    ]);
                }
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postLaboratoriumSave($id = null)
    {
        $errors = 0;
        $location = url([ADMIN, 'ranap', 'view', $id]);

        if (checkEmptyFields(['kd_dokter'], $_POST)) {
            $this->notify('failure', 'Nama dokter masih kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);
            $no_order = $this->core->setNoOrderLab();
            $query = $this->db('permintaan_lab')
              ->save([
                'noorder' => $no_order,
                'no_rawat' => revertNorawat($id),
                'tgl_permintaan' => date('Y-m-d'),
                'jam_permintaan' => date('H:i:s'),
                'tgl_sampel' => '0000-00-00',
                'jam_sampel' => '00:00:00',
                'tgl_hasil' => '0000-00-00',
                'jam_hasil' => '00:00:00',
                'dokter_perujuk' => $_POST['kd_dokter'],
                'status' => 'ranap',
                'informasi_tambahan' => $_POST['informasi_tambahan'],
                'diagnosa_klinis' => $_POST['diagnosa_klinis']
              ]);

            if ($query) {
                for ($i = 0; $i < count($_POST['kd_jenis_prw']); $i++) {
                  $query = $this->db('permintaan_pemeriksaan_lab')
                    ->save([
                      'noorder' => $no_order,
                      'kd_jenis_prw' => $_POST['kd_jenis_prw'][$i],
                      'stts_bayar' => 'Belum'
                    ]);
                }
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postResepSave($id = null)
    {
        $errors = 0;
        $location = url([ADMIN, 'ranap', 'view', $id]);

        if (checkEmptyFields(['kd_dokter'], $_POST)) {
            $this->notify('failure', 'Nama dokter masih kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);
            $no_resep = $this->core->setNoResep();
            $query = $this->db('resep_obat')
              ->save([
                'no_resep' => $no_resep,
                'tgl_perawatan' => date('Y-m-d'),
                'jam' => date('H:i:s'),
                'no_rawat' => revertNorawat($id),
                'kd_dokter' => $_POST['kd_dokter'],
                'tgl_peresepan' => date('Y-m-d'),
                'jam_peresepan' => date('H:i:s'),
                'status' => 'ranap'
              ]);

            if ($query) {
                for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
                  $this->db('resep_dokter')
                    ->save([
                      'no_resep' => $no_resep,
                      'kode_brng' => $_POST['kode_brng'][$i],
                      'jml' => $_POST['jml'][$i],
                      'aturan_pakai' => $_POST['aturan_pakai'][$i]
                    ]);
                }
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postResepRacikanSave($id = null)
    {
        $errors = 0;
        $location = url([ADMIN, 'ranap', 'view', $id]);

        if (checkEmptyFields(['kd_dokter'], $_POST)) {
            $this->notify('failure', 'Nama dokter masih kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);
            $no_resep = $this->core->setNoResep();
            $query = $this->db('resep_obat')
              ->save([
                'no_resep' => $no_resep,
                'tgl_perawatan' => date('Y-m-d'),
                'jam' => date('H:i:s'),
                'no_rawat' => revertNorawat($id),
                'kd_dokter' => $_POST['kd_dokter'],
                'tgl_peresepan' => date('Y-m-d'),
                'jam_peresepan' => date('H:i:s'),
                'status' => 'ranap'
              ]);

            if ($query) {
              $no_racik = $this->db('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
              $no_racik = $no_racik+1;
              $this->db('resep_dokter_racikan')
                ->save([
                  'no_resep' => $no_resep,
                  'no_racik' => $no_racik,
                  'nama_racik' => $_POST['nama_racik'],
                  'kd_racik' => $_POST['kd_racik'],
                  'jml_dr' => $_POST['jml_dr'],
                  'aturan_pakai' => $_POST['aturan_pakai'],
                  'keterangan' => $_POST['keterangan']
                ]);

                for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
                  $kapasitas = $this->db('databarang')->where('kode_brng', $_POST['kode_brng'][$i])->oneArray();
                  $jml = $_POST['jml_dr']*$_POST['kandungan'][$i];
                  $jml = $jml/$kapasitas['kapasitas'];
                  $this->db('resep_dokter_racikan_detail')
                    ->save([
                      'no_resep' => $no_resep,
                      'no_racik' => $no_racik,
                      'kode_brng' => $_POST['kode_brng'][$i],
                      'p1' => '1',
                      'p2' => '1',
                      'kandungan' => $_POST['kandungan'][$i],
                      'jml' => $jml
                    ]);
                }
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postKontrolSave($id = null)
    {
        $errors = 0;
        $location = url([ADMIN, 'ranap', 'view', $id]);

        if (checkEmptyFields(['kd_dokter','diagnosa','alasan1','tanggal_rujukan'], $_POST)) {
            $this->notify('failure', 'Nama dokter masih kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);
            $no_rawat = revertNorawat($id);
            $_POST['tahun'] = date('Y');
            $_POST['no_rkm_medis'] = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
            $_POST['no_antrian'] = $this->core->setNoSKDP();
            $query = $this->db('skdp_bpjs')->save($_POST);

            if ($query) {
                $this->db('booking_registrasi')
                  ->save([
                    'tanggal_booking' => date('Y-m-d'),
                    'jam_booking' => date('H:i:s'),
                    'no_rkm_medis' => $_POST['no_rkm_medis'],
                    'tanggal_periksa' => $_POST['tanggal_datang'],
                    'kd_dokter' => $_POST['kd_dokter'],
                    'kd_poli' => $this->core->getRegPeriksaInfo('kd_poli', $no_rawat),
                    'no_reg' => $this->core->setNoBooking($_POST['kd_dokter'], $_POST['tanggal_rujukan']),
                    'kd_pj' => $this->core->getRegPeriksaInfo('kd_pj', $no_rawat),
                    'limit_reg' => 0,
                    'waktu_kunjungan' => $_POST['tanggal_datang'].' '.date('H:i:s'),
                    'status' => 'Belum'
                  ]);
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postUploadDigital($id = null) {
      $dir    = $this->_uploads;
      $cntr   = 0;

      if (!is_uploaded_file($_FILES['files']['tmp_name'][0])) {
          $this->notify('failure', 'Tidak ada berkas');
      } else {
          foreach ($_FILES['files']['tmp_name'] as $image) {
              $img = new \Systems\Lib\Image();

              if ($img->load($image)) {
                  $imgName = time().$cntr++;
                  $imgPath = $dir.'/'.$id.'_'.$imgName.'.'.$img->getInfos('type');
                  $lokasi_file = 'pages/upload/'.$id.'_'.$imgName.'.'.$img->getInfos('type');
                  $img->save($imgPath);
                  $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => revertNorawat($id), 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
              } else {
                  $this->notify('failure', 'Exstensi berkas salah', 'jpg, png, gif');
              }
          }

          if ($query) {
              $this->notify('success', 'Sukses menambahkan gambar');
          };
      }
      redirect(url([ADMIN, 'ranap', 'view', $id]));
    }

    public function getAjax()
    {
        header('Content-type: text/html');
        $show = isset($_GET['show']) ? $_GET['show'] : "";
        switch($show){
        	default:
          break;
          case "databarang":
          $rows = $this->db('databarang')->like('nama_brng', '%'.$_GET['nama_brng'].'%')->where('status', '1')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kode_brng' => $row['kode_brng'],
                'nama_brng'  => $row['nama_brng']
            );
          }
          echo json_encode($array, true);
          break;
          case "aturan_pakai":
          $rows = $this->db('master_aturan_pakai')->like('aturan', '%'.$_GET['aturan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'aturan'  => $row['aturan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan":
          $rows = $this->db('jns_perawatan_inap')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan_lab":
          $rows = $this->db('jns_perawatan_lab')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan_radiologi":
          $rows = $this->db('jns_perawatan_radiologi')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "icd10":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->db('penyakit')->like('kd_penyakit', '%'.$phrase.'%')->orLike('nm_penyakit', '%'.$phrase.'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_penyakit' => $row['kd_penyakit'],
                'nm_penyakit'  => $row['nm_penyakit']
            );
          }
          echo json_encode($array, true);
          break;
          case "icd9":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->db('icd9')->like('kode', '%'.$phrase.'%')->orLike('deskripsi_panjang', '%'.$phrase.'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kode' => $row['kode'],
                'deskripsi_panjang'  => $row['deskripsi_panjang']
            );
          }
          echo json_encode($array, true);
          break;
          case "dokter":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->db('dokter')->like('kd_dokter', '%'.$phrase.'%')->orLike('nm_dokter', '%'.$phrase.'%')->where('status', '1')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_dokter' => $row['kd_dokter'],
                'nm_dokter'  => $row['nm_dokter']
            );
          }
          echo json_encode($array, true);
          break;
        }
        exit();
    }

    public function getSettings()
    {
        $this->assign['ranap'] = htmlspecialchars_array($this->options('ranap'));
        return $this->draw('settings.html', ['settings' => $this->assign]);
    }

    public function postSaveSettings()
    {
        foreach ($_POST['ranap'] as $key => $val) {
            $this->options('ranap', $key, $val);
        }
        $this->notify('success', 'Pengaturan rawat inap telah disimpan');
        redirect(url([ADMIN, 'ranap', 'settings']));
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES.'/ranap/js/admin/ranap.js');
        exit();
    }

    public function getCss()
    {
        header('Content-type: text/css');
        echo $this->draw(MODULES.'/ranap/css/admin/ranap.css');
        exit();
    }

    private function _addHeaderFiles()
    {
        // CSS
        $this->core->addCSS(url('assets/css/jquery-ui.css'));
        $this->core->addCSS(url('assets/css/jquery.timepicker.css'));
        $this->core->addCSS(url(BASE_DIR.'/assets/css/dataTables.bootstrap.min.css'));

        // JS
        $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/jquery.timepicker.js'), 'footer');
        $this->core->addJS(url(BASE_DIR.'/assets/jscripts/jquery.dataTables.min.js'), 'footer');
        $this->core->addJS(url(BASE_DIR.'/assets/jscripts/dataTables.bootstrap.min.js'), 'footer');

        // MODULE SCRIPTS
        $this->core->addCSS(url([ADMIN, 'ranap', 'css']));
        $this->core->addJS(url([ADMIN, 'ranap', 'javascript']), 'footer');
    }

}
