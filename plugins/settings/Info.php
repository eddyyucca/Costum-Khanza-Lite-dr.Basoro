<?php

return [
    'name'          =>  'Pengaturan',
    'description'   =>  'Pengaturan umum Khanza LITE.',
    'author'        =>  'Basoro',
    'version'       =>  '1.0',
    'compatibility' =>  '2020',
    'icon'          =>  'wrench',

    'install'       =>  function () use ($core) {
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `lite_options` (
            `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `module` varchar(50) NOT NULL,
            `field` varchar(250) NOT NULL,
            `value` text DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'version', '2020-01-01 00:00:00')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'theme', 'default')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'homepage', 'login')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'cekupdate', 1)");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'update_version', '2020-01-01 00:00:00')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'update_changelog', '')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'BpjsApiUrl', 'https://new-api.bpjs-kesehatan.go.id:8080/new-vclaim-rest/')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'BpjsConsID', '')");
        $core->db()->pdo()->exec("INSERT INTO `lite_options` (`module`, `field`, `value`) VALUES ('settings', 'BpjsSecretKey', '')");

    }

];
