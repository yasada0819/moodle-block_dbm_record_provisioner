<?php
// This file is part of Moodle - http://moodle.org/
require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/dbm_record_provisioner/classes/provisioner.php');
require_once($CFG->dirroot . '/blocks/dbm_record_provisioner/classes/target_resolver.php');

$courseid   = required_param('courseid',   PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$step       = optional_param('step', 'select_db', PARAM_ALPHANUMEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
// sesskey はPOST送信時のみチェック（GETアクセスでエラーにならないよう）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
}

$context = context_course::instance($courseid);
require_capability('block/dbm_record_provisioner:provision', $context);

// ブロックインスタンスとconfigを取得
$instance = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
$raw = base64_decode($instance->configdata ?? '');
$config = ($raw !== false && $raw !== '') ? @unserialize($raw) : false;
if (!is_object($config)) {
    $config = new stdClass();
}

$PAGE->set_url('/blocks/dbm_record_provisioner/provision.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
    'step'       => $step,
]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('pluginname', 'block_dbm_record_provisioner'));
$PAGE->set_heading($course->fullname);

$resolver = new block_dbm_record_provisioner_target_resolver($courseid);

// ================================================================
// STEP: select_db - DBモジュール選択
// ================================================================
if ($step === 'select_db') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dataids = optional_param_array('dataids', [], PARAM_INT);
        $dataids = array_values(array_filter($dataids));

        if (empty($dataids)) {
            redirect(new moodle_url($PAGE->url, ['step' => 'select_db']),
                get_string('error_no_db_selected', 'block_dbm_record_provisioner'),
                null, \core\output\notification::NOTIFY_ERROR);
        }

        // 選択したDBIDをconfigに一時保存して次のステップへ
        $config->selected_dataids = implode(',', $dataids);
        dbm_record_provisioner_save_config($instance, $config);

        redirect(new moodle_url($PAGE->url, ['step' => 'select_map']));
    }

    // --- 表示 ---
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_select_db_heading', 'block_dbm_record_provisioner'));

    $databases = $DB->get_records('data', ['course' => $courseid], 'name ASC', 'id, name');
    if (empty($databases)) {
        echo $OUTPUT->notification(get_string('nodatabases', 'block_dbm_record_provisioner'));
        echo $OUTPUT->footer();
        exit;
    }

    $actionurl = new moodle_url($PAGE->url, ['step' => 'select_db']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::tag('p', get_string('select_db_desc', 'block_dbm_record_provisioner'),
        ['class' => 'text-muted']);

    foreach ($databases as $db) {
        $checked = (!empty($config->selected_dataids) &&
            in_array($db->id, explode(',', $config->selected_dataids)));
        echo html_writer::div(
            html_writer::checkbox('dataids[]', $db->id, $checked, format_string($db->name),
                ['class' => 'mr-2']),
            'form-check mb-2'
        );
    }

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('next_button', 'block_dbm_record_provisioner'),
        'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('cancel'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// ================================================================
// STEP: select_map - フィールドマッピング設定
// ================================================================
if ($step === 'select_map') {

    $dataids = array_map('intval', explode(',', $config->selected_dataids ?? ''));
    if (empty($dataids)) {
        redirect(new moodle_url($PAGE->url, ['step' => 'select_db']));
    }

    // フィールド構成の一致チェック（複数DB選択時）
    // ここで不一致なら select_db に戻してアラート
    if (!block_dbm_record_provisioner_provisioner::validate_field_consistency($dataids)) {
        // 選択をリセット
        $config->selected_dataids = '';
        dbm_record_provisioner_save_config($instance, $config);
        redirect(new moodle_url($PAGE->url, ['step' => 'select_db']),
            get_string('error_field_mismatch', 'block_dbm_record_provisioner'),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $field_map      = optional_param_array('field_map',      [], PARAM_ALPHANUMEXT);
        $field_param    = optional_param_array('field_param',    [], PARAM_RAW);
        $field_grouping = optional_param_array('field_grouping', [], PARAM_INT);

        $mapping = [];
        foreach ($field_map as $fieldid => $source) {
            $mapping[(int)$fieldid] = [
                'source'   => $source,
                'param'    => $field_param[$fieldid] ?? null,
                'grouping' => $field_grouping[$fieldid] ?? 0,
            ];
        }
        $config->field_mapping = json_encode($mapping);
        dbm_record_provisioner_save_config($instance, $config);

        redirect(new moodle_url($PAGE->url, ['step' => 'target']));
    }

    // --- 表示 ---
    // 選択されたDBのフィールド一覧を最初のDBから取得（一致チェック済みなので代表でOK）
    $first_dataid  = $dataids[0];
    $fields        = $DB->get_records('data_fields', ['dataid' => $first_dataid], 'name ASC');

    // 保存済みマッピングがあればそれを使う。なければサイト管理のデフォルトを適用
    if (!empty($config->field_mapping)) {
        $saved_mapping = json_decode($config->field_mapping, true);
    } else {
        // サイト管理のデフォルトマッピングをフィールド名で照合
        $default_name_to_source = [];
        for ($i = 1; $i <= 5; $i++) {
            $name   = get_config('block_dbm_record_provisioner', "mapping_name_{$i}");
            $source = get_config('block_dbm_record_provisioner', "mapping_source_{$i}");
            if (!empty($name) && !empty($source)) {
                $default_name_to_source[$name] = $source;
            }
        }

        $saved_mapping = [];
        foreach ($fields as $field) {
            $matched_source = $default_name_to_source[$field->name] ?? 'blank';
            $saved_mapping[$field->id] = [
                'source'   => $matched_source,
                'param'    => $matched_source === 'profile' ? 'idnumber' : null,
                'grouping' => 0,
            ];
        }
    }

    // 選択中のDB名一覧（確認表示用）
    $db_names = [];
    foreach ($dataids as $did) {
        $db = $DB->get_record('data', ['id' => $did], 'name');
        if ($db) $db_names[] = format_string($db->name);
    }

    // ソースの選択肢（idnumberはprofileに統合）
    $source_options = [
        'blank'    => get_string('source_blank',    'block_dbm_record_provisioner'),
        'fullname' => get_string('source_fullname',  'block_dbm_record_provisioner'),
        'profile'  => get_string('source_profile',   'block_dbm_record_provisioner'),
        'group'    => get_string('source_group',     'block_dbm_record_provisioner'),
        'csv_col'  => get_string('source_csv_col',   'block_dbm_record_provisioner'),
    ];

    $profile_fields = $resolver->get_profile_fields();
    $course_groupings = groups_get_all_groupings($courseid);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_select_map_heading', 'block_dbm_record_provisioner'));

    // 選択中DBの確認バナー
    echo html_writer::tag('div',
        get_string('selected_databases', 'block_dbm_record_provisioner')
        . ' ' . html_writer::tag('strong', implode('、', $db_names)),
        ['class' => 'alert alert-info']
    );

    $actionurl = new moodle_url($PAGE->url, ['step' => 'select_map']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::tag('p', get_string('field_mapping_desc', 'block_dbm_record_provisioner'),
        ['class' => 'text-muted']);

    $table       = new html_table();
    $table->head = [
        get_string('db_field_name',   'block_dbm_record_provisioner'),
        get_string('mapping_source',  'block_dbm_record_provisioner'),
        get_string('mapping_param',   'block_dbm_record_provisioner'),
    ];
    $table->data = [];

    foreach ($fields as $field) {
        $current_source = $saved_mapping[$field->id]['source'] ?? 'blank';
        $current_param  = $saved_mapping[$field->id]['param']  ?? 0;

        // ソース選択ドロップダウン（JS連動のためdata属性にfield_idを持たせる）
        $source_select = html_writer::select(
            $source_options,
            "field_map[{$field->id}]",
            $current_source,
            false,
            [
                'class'           => 'custom-select custom-select-sm dbm-source-select',
                'data-fieldid'    => $field->id,
                'onchange'        => 'dbmUpdateParam(this)',
            ]
        );

        // profile用ドロップダウン（source=profileのときだけ表示）
        // サイト管理で有効化されたmdl_userフィールドを先頭に追加
        $enabled_user_fields = get_config('block_dbm_record_provisioner', 'enabled_user_fields');
        $enabled_user_fields = $enabled_user_fields ? explode(',', $enabled_user_fields) : ['idnumber'];
        $user_field_labels = [
            'idnumber'    => 'idnumber（学籍番号）',
            'email'       => 'email',
            'department'  => 'department',
            'institution' => 'institution',
            'city'        => 'city',
            'country'     => 'country',
            'phone1'      => 'phone1',
            'phone2'      => 'phone2',
        ];
        // mdl_userフィールドのラベルはMoodleコア言語パックから引く（言語置換に追従）
        $user_field_labels = [
            'idnumber'    => 'idnumber（' . get_string('idnumber')    . '）',
            'email'       => 'email（'    . get_string('email')       . '）',
            'department'  => 'department（' . get_string('department') . '）',
            'institution' => 'institution（' . get_string('institution') . '）',
            'city'        => 'city（'    . get_string('city')         . '）',
            'country'     => 'country（' . get_string('country')      . '）',
            'phone1'      => 'phone1（'  . get_string('phone1')       . '）',
            'phone2'      => 'phone2（'  . get_string('phone2')       . '）',
        ];
        $profile_options = [];
        foreach ($enabled_user_fields as $uf) {
            $uf = trim($uf);
            if (isset($user_field_labels[$uf])) {
                $profile_options[$uf] = $user_field_labels[$uf];
            }
        }
        foreach ($profile_fields as $pf) {
            $profile_options[$pf->id] = format_string($pf->name);
        }
        $profile_select = html_writer::tag('div',
            html_writer::select(
                $profile_options,
                "field_param[{$field->id}]",
                ($current_source === 'profile') ? $current_param : 'idnumber',
                ['' => get_string('select_profile_field', 'block_dbm_record_provisioner')],
                ['class' => 'custom-select custom-select-sm']
            ),
            [
                'class' => 'dbm-param-profile' . ($current_source === 'profile' ? '' : ' d-none'),
                'id'    => "param_profile_{$field->id}",
            ]
        );

        // group用グルーピング選択ドロップダウン（source=groupのときだけ表示）
        $grouping_options = [0 => get_string('no_grouping', 'block_dbm_record_provisioner')];
        foreach ($course_groupings as $g) {
            $grouping_options[$g->id] = format_string($g->name);
        }
        $group_select = html_writer::tag('div',
            html_writer::select(
                $grouping_options,
                "field_grouping[{$field->id}]",
                ($current_source === 'group') ? ($saved_mapping[$field->id]['grouping'] ?? 0) : 0,
                false,
                ['class' => 'custom-select custom-select-sm']
            ),
            [
                'class' => 'dbm-param-group' . ($current_source === 'group' ? '' : ' d-none'),
                'id'    => "param_group_{$field->id}",
            ]
        );

        // パラメータ不要のときのダッシュ
        $no_param = html_writer::tag('div', '—',
            [
                'class' => 'text-muted' . (!in_array($current_source, ['profile', 'group']) ? '' : ' d-none'),
                'id'    => "param_none_{$field->id}",
            ]
        );

        $table->data[] = [
            format_string($field->name) . html_writer::tag('small', ' (' . $field->type . ')', ['class' => 'text-muted']),
            $source_select,
            $no_param . $profile_select . $group_select,
        ];
    }
    echo html_writer::table($table);

    // JS：ソース選択に連動してパラメータ欄を切り替える
    $js = <<<JS
function dbmUpdateParam(select) {
    var fieldId = select.getAttribute('data-fieldid');
    var source  = select.value;

    var noneEl    = document.getElementById('param_none_'    + fieldId);
    var profileEl = document.getElementById('param_profile_' + fieldId);
    var groupEl   = document.getElementById('param_group_'   + fieldId);

    if (noneEl)    noneEl.classList.toggle('d-none',    source === 'profile' || source === 'group');
    if (profileEl) profileEl.classList.toggle('d-none', source !== 'profile');
    if (groupEl)   groupEl.classList.toggle('d-none',   source !== 'group');
}
JS;
    echo html_writer::tag('script', $js);

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('next_button', 'block_dbm_record_provisioner'),
        'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link(
        new moodle_url($PAGE->url, ['step' => 'select_db']),
        get_string('back_button', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// ================================================================
// STEP: target - 対象者指定
// ================================================================
if ($step === 'target') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = required_param('target_mode', PARAM_ALPHA);

        $params = [];
        switch ($mode) {
            case 'role':
                $params['roleid'] = required_param('roleid', PARAM_INT);
                break;
            case 'cohort':
                $params['cohortid'] = required_param('cohortid', PARAM_INT);
                break;
            case 'profile':
                $params['fieldid'] = required_param('profile_fieldid', PARAM_INT);
                $params['value']   = required_param('profile_value',   PARAM_TEXT);
                break;
            case 'csv':
                // CSVアップロード処理
                $csv_field   = optional_param('csv_field', 'idnumber', PARAM_ALPHA);
                $csv_content = dbm_record_provisioner_parse_csv_upload($courseid, $csv_field);
                $params['userids'] = $csv_content['userids'];
                $params['not_found'] = $csv_content['not_found'];
                break;
            case 'group':
                $params['groupid'] = required_param('groupid', PARAM_INT);
                break;
            // 'all' はparams不要
        }

        // セッションに対象者情報を保存して次へ
        $SESSION->dbm_provisioner = [
            'target_mode'   => $mode,
            'target_params' => $params,
            'csv_not_found' => $params['not_found'] ?? [],
        ];

        redirect(new moodle_url($PAGE->url, ['step' => 'preview']));
    }

    // --- 表示 ---
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_target_heading', 'block_dbm_record_provisioner'));

    $actionurl = new moodle_url($PAGE->url, ['step' => 'target']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl,
        'enctype' => 'multipart/form-data']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    // 対象者モード選択（ラジオボタン）
    $modes = ['all', 'role', 'cohort', 'profile', 'csv', 'group'];
    foreach ($modes as $mode) {
        echo html_writer::start_div('card mb-2');
        echo html_writer::start_div('card-body py-2');
        echo html_writer::tag('div',
            html_writer::empty_tag('input', [
                'type'  => 'radio',
                'name'  => 'target_mode',
                'value' => $mode,
                'id'    => 'mode_' . $mode,
                'class' => 'mr-2',
            ]) .
            html_writer::tag('label',
                get_string('target_mode_' . $mode, 'block_dbm_record_provisioner',
                    ($mode === 'csv') ? get_string('idnumber') : null),
                ['for' => 'mode_' . $mode, 'class' => 'mb-0 font-weight-bold']
            ),
            ['class' => 'form-check']
        );

        // モードごとの追加入力
        echo html_writer::start_div('mt-2 ml-4 mode-options', ['id' => 'options_' . $mode]);
        switch ($mode) {
            case 'role':
                $roles   = $resolver->get_course_roles();
                $roptions = [0 => get_string('select_role', 'block_dbm_record_provisioner')];
                foreach ($roles as $r) {
                    $roptions[$r->id] = format_string($r->localname);
                }
                echo html_writer::select($roptions, 'roleid', 0, false,
                    ['class' => 'custom-select custom-select-sm w-auto']);
                break;

            case 'cohort':
                $cohorts = $resolver->get_course_cohorts();
                $options = [0 => get_string('select_cohort', 'block_dbm_record_provisioner')];
                foreach ($cohorts as $c) {
                    $options[$c->id] = format_string($c->name);
                }
                echo html_writer::select($options, 'cohortid', 0, false,
                    ['class' => 'custom-select custom-select-sm w-auto']);
                break;

            case 'profile':
                $pfields  = $resolver->get_profile_fields();
                $poptions = [0 => get_string('select_profile_field', 'block_dbm_record_provisioner')];
                foreach ($pfields as $pf) {
                    $poptions[$pf->id] = format_string($pf->name);
                }
                echo html_writer::select($poptions, 'profile_fieldid', 0, false,
                    ['class' => 'custom-select custom-select-sm w-auto mr-2']);
                echo html_writer::empty_tag('input', [
                    'type'        => 'text',
                    'name'        => 'profile_value',
                    'placeholder' => get_string('profile_value_placeholder', 'block_dbm_record_provisioner'),
                    'class'       => 'form-control form-control-sm w-auto d-inline-block',
                ]);
                break;

            case 'csv':
                // idnumber / username 選択
                echo html_writer::tag('div',
                    html_writer::tag('div',
                        html_writer::empty_tag('input', [
                            'type'    => 'radio',
                            'name'    => 'csv_field',
                            'value'   => 'idnumber',
                            'id'      => 'csv_field_idnumber',
                            'class'   => 'mr-1',
                            'checked' => 'checked',
                        ]) .
                        html_writer::tag('label',
                            get_string('idnumber'),
                            ['for' => 'csv_field_idnumber', 'class' => 'mr-3 mb-0']
                        ) .
                        html_writer::empty_tag('input', [
                            'type'  => 'radio',
                            'name'  => 'csv_field',
                            'value' => 'username',
                            'id'    => 'csv_field_username',
                            'class' => 'mr-1',
                        ]) .
                        html_writer::tag('label',
                            get_string('username'),
                            ['for' => 'csv_field_username', 'class' => 'mb-0']
                        ),
                        ['class' => 'form-check form-check-inline mb-1']
                    ),
                    ['class' => 'mb-1']
                );
                echo html_writer::tag('p',
                    get_string('csv_upload_desc', 'block_dbm_record_provisioner',
                        get_string('idnumber') . ' / ' . get_string('username')),
                    ['class' => 'small text-muted mb-1']);
                echo html_writer::empty_tag('input', [
                    'type'   => 'file',
                    'name'   => 'csvfile',
                    'class'  => 'form-control-file',
                    'accept' => '.csv',
                ]);
                break;

            case 'group':
                $groups  = $resolver->get_course_groups();
                $goptions = [0 => get_string('select_group', 'block_dbm_record_provisioner')];
                foreach ($groups as $g) {
                    $goptions[$g->id] = format_string($g->name);
                }
                echo html_writer::select($goptions, 'groupid', 0, false,
                    ['class' => 'custom-select custom-select-sm w-auto']);
                break;
        }
        echo html_writer::end_div(); // mode-options
        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
    }

    // 重複時の動作選択
    echo $OUTPUT->heading(get_string('duplicate_handling', 'block_dbm_record_provisioner'), 4);
    $dup_options = [
        'skip' => get_string('duplicate_skip', 'block_dbm_record_provisioner'),
        'add'  => get_string('duplicate_add',  'block_dbm_record_provisioner'),
    ];
    foreach ($dup_options as $val => $label) {
        echo html_writer::tag('div',
            html_writer::empty_tag('input', [
                'type'    => 'radio',
                'name'    => 'duplicate',
                'value'   => $val,
                'id'      => 'dup_' . $val,
                'class'   => 'mr-2',
                'checked' => ($val === 'skip') ? 'checked' : null,
            ]) .
            html_writer::tag('label', $label, ['for' => 'dup_' . $val]),
            ['class' => 'form-check mb-1']
        );
    }

    // ボタン
    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('preview_button', 'block_dbm_record_provisioner'),
        'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link(
        new moodle_url($PAGE->url, ['step' => 'select_map']),
        get_string('back_button', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// ================================================================
// STEP: preview - 生成件数確認
// ================================================================
if ($step === 'preview') {

    $session_data = $SESSION->dbm_provisioner ?? null;
    if (!$session_data) {
        redirect(new moodle_url($PAGE->url, ['step' => 'target']));
    }

    $target_mode   = $session_data['target_mode'];
    $target_params = $session_data['target_params'];
    $duplicate     = $session_data['duplicate'] ?? 'skip';

    // 対象ユーザー解決
    $userids = $resolver->resolve($target_mode, $target_params);

    $dataids = array_map('intval', explode(',', $config->selected_dataids ?? ''));
    $mapping = json_decode($config->field_mapping ?? '{}', true);

    $provisioner = new block_dbm_record_provisioner_provisioner(
        $dataids, $mapping, $courseid, $USER->id
    );
    $preview = $provisioner->preview($userids, $duplicate);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_preview_heading', 'block_dbm_record_provisioner'));

    // 件数サマリー
    echo html_writer::start_div('alert alert-info');
    echo html_writer::tag('p', get_string('preview_will_insert', 'block_dbm_record_provisioner',
        $preview['will_insert']));
    if ($preview['will_skip'] > 0) {
        echo html_writer::tag('p', get_string('preview_will_skip', 'block_dbm_record_provisioner',
            $preview['will_skip']));
    }
    // DB別明細
    if (count($dataids) > 1 && !empty($preview['per_db'])) {
        $table = new html_table();
        $table->head = [
            get_string('database', 'data'),
            get_string('preview_will_insert', 'block_dbm_record_provisioner', ''),
            get_string('preview_will_skip',   'block_dbm_record_provisioner', ''),
        ];
        $table->data = [];
        foreach ($preview['per_db'] as $did => $counts) {
            $db = $DB->get_record('data', ['id' => $did], 'name');
            $table->data[] = [
                $db ? format_string($db->name) : "ID:{$did}",
                $counts['will_insert'],
                $counts['will_skip'],
            ];
        }
        echo html_writer::table($table);
    }
    echo html_writer::end_div();

    // CSVで見つからなかったID一覧
    $csv_not_found = $session_data['csv_not_found'] ?? [];
    if (!empty($csv_not_found)) {
        echo html_writer::start_div('alert alert-warning');
        echo html_writer::tag('p',
            get_string('csv_not_found_warning', 'block_dbm_record_provisioner',
                count($csv_not_found)),
            ['class' => 'mb-1 font-weight-bold']
        );
        echo html_writer::tag('ul',
            implode('', array_map(
                fn($id) => html_writer::tag('li', s($id), ['class' => 'small']),
                $csv_not_found
            ))
        );
        echo html_writer::end_div();
    }

    // 実行フォーム
    $actionurl = new moodle_url($PAGE->url, ['step' => 'execute']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',   'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'duplicate', 'value' => $duplicate]);

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('execute_button', 'block_dbm_record_provisioner'),
        'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link(
        new moodle_url($PAGE->url, ['step' => 'target']),
        get_string('back_button', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// ================================================================
// STEP: execute - 実行
// ================================================================
if ($step === 'execute') {

    $session_data = $SESSION->dbm_provisioner ?? null;
    if (!$session_data) {
        redirect(new moodle_url($PAGE->url, ['step' => 'target']));
    }

    $duplicate     = optional_param('duplicate', 'skip', PARAM_ALPHA);
    $target_mode   = $session_data['target_mode'];
    $target_params = $session_data['target_params'];

    $userids = $resolver->resolve($target_mode, $target_params);
    $dataids = array_map('intval', explode(',', $config->selected_dataids ?? ''));
    $mapping = json_decode($config->field_mapping ?? '{}', true);

    $provisioner = new block_dbm_record_provisioner_provisioner(
        $dataids, $mapping, $courseid, $USER->id
    );

    $result = $provisioner->provision($userids, $duplicate);

    // configdataに直近バッチのrecord_id範囲を保存
    $config->last_provisioned_from = $result['min_record_id'];
    $config->last_provisioned_to   = $result['max_record_id'];
    dbm_record_provisioner_save_config($instance, $config);

    // セッションクリア
    unset($SESSION->dbm_provisioner);

    // --- 完了画面 ---
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_complete_heading', 'block_dbm_record_provisioner'));

    echo html_writer::start_div('alert alert-success');
    echo html_writer::tag('p', get_string('result_inserted', 'block_dbm_record_provisioner',
        $result['inserted']));
    if ($result['skipped'] > 0) {
        echo html_writer::tag('p', get_string('result_skipped', 'block_dbm_record_provisioner',
            $result['skipped']));
    }
    // DB別明細
    if (count($dataids) > 1 && !empty($result['per_db'])) {
        $table = new html_table();
        $table->head = [
            get_string('database', 'data'),
            get_string('result_inserted', 'block_dbm_record_provisioner', ''),
            get_string('result_skipped',  'block_dbm_record_provisioner', ''),
        ];
        $table->data = [];
        foreach ($result['per_db'] as $did => $counts) {
            $db = $DB->get_record('data', ['id' => $did], 'name');
            $table->data[] = [
                $db ? format_string($db->name) : "ID:{$did}",
                $counts['inserted'],
                $counts['skipped'],
            ];
        }
        echo html_writer::table($table);
    }
    echo html_writer::end_div();

    // グループ割り当てへのボタン（block_data_groupassign が存在する場合のみ表示）
    $groupassign_exists = $DB->record_exists('block_instances', ['blockname' => 'data_groupassign',
        'parentcontextid' => $context->id]);

    echo html_writer::start_div('mt-3');
    if ($groupassign_exists && !empty($dataids)) {
        $groupassign_url = new moodle_url('/blocks/data_groupassign/assign_groups.php', [
            'courseid' => $courseid,
            'dataid'   => $dataids[0],
        ]);
        echo html_writer::link(
            $groupassign_url,
            get_string('goto_groupassign', 'block_dbm_record_provisioner'),
            ['class' => 'btn btn-success mr-2']
        );
    }
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('backtocourse', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// ================================================================
// ユーティリティ関数
// ================================================================

/**
 * configdataをブロックインスタンスに保存する
 */
function dbm_record_provisioner_save_config(stdClass $instance, stdClass $config): void {
    global $DB;
    $instance->configdata = base64_encode(serialize($config));
    $DB->update_record('block_instances', $instance);
}

/**
 * CSVファイルをアップロードして学籍番号→useridリストを返す
 *
 * @return array ['userids' => int[], 'not_found' => string[]]
 */
function dbm_record_provisioner_parse_csv_upload(int $courseid, string $csv_field = 'idnumber'): array {
    global $DB;

    $file = $_FILES['csvfile'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['userids' => [], 'not_found' => []];
    }

    // 対応するMoodleフィールド名（idnumber or username）
    $db_field  = ($csv_field === 'username') ? 'username' : 'idnumber';

    $lines     = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $userids   = [];
    $not_found = [];

    foreach ($lines as $line) {
        $value = trim(str_getcsv($line)[0] ?? '');
        // ヘッダー行スキップ（idnumber / username どちらでも）
        if ($value === '' || $value === 'idnumber' || $value === 'username') {
            continue;
        }
        $user = $DB->get_record('user', [$db_field => $value], 'id');
        if ($user) {
            $userids[] = $user->id;
        } else {
            $not_found[] = $value;
        }
    }

    return ['userids' => array_unique($userids), 'not_found' => $not_found];
}
