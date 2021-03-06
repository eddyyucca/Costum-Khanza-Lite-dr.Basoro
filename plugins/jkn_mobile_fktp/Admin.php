<?php

namespace Plugins\JKN_Mobile_FKTP;

use Systems\AdminModule;

class Admin extends AdminModule
{

    public function navigation()
    {
        return [
            'Kelola' => 'index',
            'Pengaturan' => 'settings',
        ];
    }

    public function getIndex()
    {
        return $this->draw('index.html');
    }

    public function getSettings()
    {
        $this->_addHeaderFiles();
        $this->assign['title'] = 'Pengaturan Modul JKN Mobile FKTP';
        $this->assign['poliklinik'] = $this->_getPoliklinik($this->options->get('jkn_mobile_fktp.display'));
        $this->assign['jkn_mobile_fktp'] = htmlspecialchars_array($this->options('jkn_mobile_fktp'));
        return $this->draw('settings.html', ['settings' => $this->assign]);
    }

    private function _getPoliklinik($kd_poli = null)
    {
        $result = [];
        $rows = $this->db('poliklinik')->toArray();

        if (!$kd_poli) {
            $kd_poliArray = [];
        } else {
            $kd_poliArray = explode(',', $kd_poli);
        }

        foreach ($rows as $row) {
            if (empty($kd_poliArray)) {
                $attr = '';
            } else {
                if (in_array($row['kd_poli'], $kd_poliArray)) {
                    $attr = 'selected';
                } else {
                    $attr = '';
                }
            }
            $result[] = ['kd_poli' => $row['kd_poli'], 'nm_poli' => $row['nm_poli'], 'attr' => $attr];
        }
        return $result;
    }

    public function postSaveSettings()
    {
        $_POST['jkn_mobile_fktp']['display'] = implode(',', $_POST['jkn_mobile_fktp']['display']);
        foreach ($_POST['jkn_mobile_fktp'] as $key => $val) {
            $this->options('jkn_mobile_fktp', $key, $val);
        }
        $this->notify('success', 'Pengaturan telah disimpan');
        redirect(url([ADMIN, 'jkn_mobile_fktp', 'settings']));
    }

    private function _addHeaderFiles()
    {
        // CSS
        $this->core->addCSS(url('assets/css/jquery-ui.css'));
        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));

        // JS
        $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'), 'footer');

    }

}
