<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

class block_dbm_record_provisioner_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        $mform->addElement('header', 'config_header',
            get_string('blocksettings', 'block'));

        // ブロックタイトル（任意）
        $mform->addElement('text', 'config_title',
            get_string('config_title', 'block_dbm_record_provisioner'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addHelpButton('config_title', 'config_title', 'block_dbm_record_provisioner');

        // --- 以下はprovision.phpで設定・保存されるフィールド ---
        // config_field_mapping      : json形式のマッピング設定
        // config_last_provisioned_from : 直近バッチ record_id 最小値
        // config_last_provisioned_to   : 直近バッチ record_id 最大値
        // これらはフォームに表示せず、provision.php / delete.php が直接読み書きする。
        $mform->addElement('hidden', 'config_field_mapping', '');
        $mform->setType('config_field_mapping', PARAM_RAW);

        $mform->addElement('hidden', 'config_last_provisioned_from', 0);
        $mform->setType('config_last_provisioned_from', PARAM_INT);

        $mform->addElement('hidden', 'config_last_provisioned_to', 0);
        $mform->setType('config_last_provisioned_to', PARAM_INT);
    }
}
