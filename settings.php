<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // ================================================================
    // 管理者向け一括生成ツールへのリンク
    // ================================================================
    $settings->add(new admin_setting_heading(
        'block_dbm_record_provisioner/heading_bulk',
        get_string('bulk_provision_heading', 'block_dbm_record_provisioner'),
        html_writer::link(
            new moodle_url('/blocks/dbm_record_provisioner/provision_admin.php'),
            get_string('bulk_provision_link', 'block_dbm_record_provisioner'),
            ['class' => 'btn btn-primary btn-sm']
        )
    ));

    // ================================================================
    // ① mdl_user 標準フィールドの選択肢
    // ================================================================
    $settings->add(new admin_setting_heading(
        'block_dbm_record_provisioner/heading_userfields',
        get_string('settings_userfields_heading', 'block_dbm_record_provisioner'),
        get_string('settings_userfields_desc',    'block_dbm_record_provisioner')
    ));

    // 有効にするmdl_userフィールドをチェックボックスで選択
    // ラベルはMoodleコア言語パックから引用（言語置換に自動追従）
    $user_field_options = [
        'idnumber'    => 'idnumber（' . get_string('idnumber')    . '）',
        'email'       => 'email（'    . get_string('email')       . '）',
        'department'  => 'department（' . get_string('department') . '）',
        'institution' => 'institution（' . get_string('institution') . '）',
        'city'        => 'city（'    . get_string('city')         . '）',
        'country'     => 'country（' . get_string('country')      . '）',
        'phone1'      => 'phone1（'  . get_string('phone1')       . '）',
        'phone2'      => 'phone2（'  . get_string('phone2')       . '）',
    ];

    $settings->add(new admin_setting_configmulticheckbox(
        'block_dbm_record_provisioner/enabled_user_fields',
        get_string('settings_enabled_user_fields',      'block_dbm_record_provisioner'),
        get_string('settings_enabled_user_fields_desc', 'block_dbm_record_provisioner'),
        ['idnumber' => 1], // デフォルトはidnumberのみ有効
        $user_field_options
    ));

    // ================================================================
    // ② デフォルトマッピング（フィールド名完全一致）
    // ================================================================
    $settings->add(new admin_setting_heading(
        'block_dbm_record_provisioner/heading_mapping',
        get_string('settings_mapping_heading', 'block_dbm_record_provisioner'),
        get_string('settings_mapping_desc',    'block_dbm_record_provisioner')
    ));

    // ソースの選択肢（select_mapと同じ並び）
    $source_options = [
        ''         => get_string('settings_mapping_nosource', 'block_dbm_record_provisioner'),
        'blank'    => get_string('source_blank',    'block_dbm_record_provisioner'),
        'fullname' => get_string('source_fullname', 'block_dbm_record_provisioner'),
        'profile'  => get_string('source_profile',  'block_dbm_record_provisioner'),
        'group'    => get_string('source_group',    'block_dbm_record_provisioner'),
        'csv_col'  => get_string('source_csv_col',  'block_dbm_record_provisioner'),
    ];

    // 5ペア分のマッピング設定
    $default_mappings = [
        1 => ['name' => '氏名',     'source' => 'fullname'],
        2 => ['name' => '学籍番号', 'source' => 'profile'],
        3 => ['name' => '班番号',   'source' => 'group'],
        4 => ['name' => '',         'source' => ''],
        5 => ['name' => '',         'source' => ''],
    ];

    for ($i = 1; $i <= 5; $i++) {
        // フィールド名（完全一致）：注釈なし
        $settings->add(new admin_setting_configtext(
            "block_dbm_record_provisioner/mapping_name_{$i}",
            get_string('settings_mapping_row', 'block_dbm_record_provisioner', $i)
                . '　' . get_string('settings_mapping_fieldname', 'block_dbm_record_provisioner'),
            '', // 注釈なし
            $default_mappings[$i]['name'] ?? '',
            PARAM_TEXT
        ));

        // デフォルトソース：注釈なし
        $settings->add(new admin_setting_configselect(
            "block_dbm_record_provisioner/mapping_source_{$i}",
            get_string('settings_mapping_row', 'block_dbm_record_provisioner', $i)
                . '　' . get_string('settings_mapping_source', 'block_dbm_record_provisioner'),
            '', // 注釈なし
            $default_mappings[$i]['source'] ?? '',
            $source_options
        ));
    }
}
