<?php
// This file is part of Moodle - http://moodle.org/
require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/dbm_record_provisioner/classes/provisioner.php');
require_once($CFG->dirroot . '/blocks/dbm_record_provisioner/classes/target_resolver.php');

// 管理者専用ページ
require_login();
require_capability('moodle/site:config', context_system::instance());

$step = optional_param('step', 'select_category', PARAM_ALPHANUMEXT);

$PAGE->set_url('/blocks/dbm_record_provisioner/provision_admin.php', ['step' => $step]);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('bulk_provision_heading', 'block_dbm_record_provisioner'));
$PAGE->set_heading(get_string('bulk_provision_heading', 'block_dbm_record_provisioner'));
$PAGE->set_pagelayout('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
}

// セッションキー（通常のprovision.phpと分離）
$SESSION_KEY = 'dbm_provisioner_admin';

// ================================================================
// ユーティリティ：カテゴリ以下のコース一覧を再帰的に取得
// ================================================================
function dbm_admin_get_courses_in_category(int $categoryid): array {
    global $DB;

    // このカテゴリ以下の全カテゴリID（サブカテゴリ含む）を再帰取得
    $category_ids = [$categoryid];
    $queue        = [$categoryid];

    while (!empty($queue)) {
        $parent = array_shift($queue);
        $children = $DB->get_records('course_categories', ['parent' => $parent], '', 'id');
        foreach ($children as $child) {
            $category_ids[] = $child->id;
            $queue[]        = $child->id;
        }
    }

    if (empty($category_ids)) {
        return [];
    }

    list($in_sql, $in_params) = $DB->get_in_or_equal($category_ids, SQL_PARAMS_NAMED, 'cat');
    $sql = "SELECT id, fullname, shortname, category
              FROM {course}
             WHERE category $in_sql
               AND id != :siteid
             ORDER BY category ASC, fullname ASC";
    $in_params['siteid'] = SITEID;

    return $DB->get_records_sql($sql, $in_params);
}

// ================================================================
// ユーティリティ：コース一覧からDBモジュール一覧を取得
// ================================================================
function dbm_admin_get_databases_in_courses(array $courseids): array {
    global $DB;

    if (empty($courseids)) {
        return [];
    }

    list($in_sql, $in_params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
    $sql = "SELECT d.id, d.name, d.course, c.fullname as coursename
              FROM {data} d
              JOIN {course} c ON c.id = d.course
             WHERE d.course $in_sql
             ORDER BY c.fullname ASC, d.name ASC";

    return $DB->get_records_sql($sql, $in_params);
}

// ================================================================
// STEP: select_category - カテゴリ選択
// ================================================================
if ($step === 'select_category') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $categoryid = required_param('categoryid', PARAM_INT);
        if ($categoryid <= 0) {
            redirect($PAGE->url,
                get_string('error_no_category_selected', 'block_dbm_record_provisioner'),
                null, \core\output\notification::NOTIFY_ERROR);
        }
        $SESSION->$SESSION_KEY = ['categoryid' => $categoryid];
        redirect(new moodle_url($PAGE->url, ['step' => 'select_db']));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('admin_step_select_category', 'block_dbm_record_provisioner'));

    // カテゴリ一覧（ネストを表現するためcoursecat APIを使用）
    $categories = core_course_category::make_categories_list();

    $actionurl = new moodle_url($PAGE->url, ['step' => 'select_category']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::tag('p',
        get_string('admin_select_category_desc', 'block_dbm_record_provisioner'),
        ['class' => 'text-muted']);

    $saved_cat = $SESSION->$SESSION_KEY['categoryid'] ?? 0;
    echo html_writer::select(
        $categories,
        'categoryid',
        $saved_cat,
        [0 => get_string('select_category', 'block_dbm_record_provisioner')],
        ['class' => 'custom-select w-auto d-block mb-3']
    );

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('next_button', 'block_dbm_record_provisioner'),
        'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'blocksettingdbm_record_provisioner']),
        get_string('cancel'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// セッション確認（select_category以降は必須）
$session_data = $SESSION->$SESSION_KEY ?? null;
if (!$session_data || empty($session_data['categoryid'])) {
    redirect(new moodle_url($PAGE->url, ['step' => 'select_category']));
}
$categoryid = (int)$session_data['categoryid'];

// ================================================================
// STEP: select_db - DB選択・フィールド一致チェック
// ================================================================
if ($step === 'select_db') {

    $courses   = dbm_admin_get_courses_in_category($categoryid);
    $courseids = array_keys($courses);
    $databases = dbm_admin_get_databases_in_courses($courseids);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected_dbids = optional_param_array('dbids', [], PARAM_INT);
        $selected_dbids = array_values(array_filter($selected_dbids));

        if (empty($selected_dbids)) {
            redirect(new moodle_url($PAGE->url, ['step' => 'select_db']),
                get_string('error_no_db_selected', 'block_dbm_record_provisioner'),
                null, \core\output\notification::NOTIFY_ERROR);
        }

        // フィールド構成の一致チェック
        if (!block_dbm_record_provisioner_provisioner::validate_field_consistency($selected_dbids)) {
            redirect(new moodle_url($PAGE->url, ['step' => 'select_db']),
                get_string('error_field_mismatch', 'block_dbm_record_provisioner'),
                null, \core\output\notification::NOTIFY_ERROR);
        }

        $SESSION->$SESSION_KEY = array_merge($session_data, [
            'selected_dbids' => $selected_dbids,
        ]);
        redirect(new moodle_url($PAGE->url, ['step' => 'select_map']));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('admin_step_select_db', 'block_dbm_record_provisioner'));

    // 選択中カテゴリ名を表示
    $category = core_course_category::get($categoryid, IGNORE_MISSING);
    if ($category) {
        echo html_writer::tag('div',
            get_string('admin_selected_category', 'block_dbm_record_provisioner')
            . ' ' . html_writer::tag('strong', $category->get_formatted_name()),
            ['class' => 'alert alert-info']
        );
    }

    if (empty($databases)) {
        echo $OUTPUT->notification(
            get_string('admin_no_databases_in_category', 'block_dbm_record_provisioner'),
            \core\output\notification::NOTIFY_WARNING
        );
        echo html_writer::link(
            new moodle_url($PAGE->url, ['step' => 'select_category']),
            get_string('back_button', 'block_dbm_record_provisioner'),
            ['class' => 'btn btn-secondary']
        );
        echo $OUTPUT->footer();
        exit;
    }

    $actionurl = new moodle_url($PAGE->url, ['step' => 'select_db']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::tag('p',
        get_string('admin_select_db_desc', 'block_dbm_record_provisioner'),
        ['class' => 'text-muted']);

    $saved_dbids = $session_data['selected_dbids'] ?? [];
    foreach ($databases as $db) {
        $checked = in_array($db->id, $saved_dbids);
        $label   = format_string($db->name) . html_writer::tag('small',
            '（' . format_string($db->coursename) . '）',
            ['class' => 'text-muted ml-1']
        );
        echo html_writer::div(
            html_writer::checkbox('dbids[]', $db->id, $checked, $label, ['class' => 'mr-2']),
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
        new moodle_url($PAGE->url, ['step' => 'select_category']),
        get_string('back_button', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// ================================================================
// STEP: select_map - マッピング確認・調整
// ================================================================
if ($step === 'select_map') {

    $selected_dbids = $session_data['selected_dbids'] ?? [];
    if (empty($selected_dbids)) {
        redirect(new moodle_url($PAGE->url, ['step' => 'select_db']));
    }

    $first_dataid = $selected_dbids[0];
    $fields       = $DB->get_records('data_fields', ['dataid' => $first_dataid], 'name ASC');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $field_map      = optional_param_array('field_map',      [], PARAM_ALPHANUMEXT);
        $field_param    = optional_param_array('field_param',    [], PARAM_RAW);
        $field_grouping = optional_param_array('field_grouping', [], PARAM_INT);

        $mapping = [];
        foreach ($field_map as $fieldid => $source) {
            $mapping[(int)$fieldid] = [
                'source'   => $source,
                'param'    => $field_param[$fieldid]    ?? null,
                'grouping' => $field_grouping[$fieldid] ?? 0,
            ];
        }

        $SESSION->$SESSION_KEY = array_merge($session_data, [
            'field_mapping' => json_encode($mapping),
        ]);
        redirect(new moodle_url($PAGE->url, ['step' => 'target']));
    }

    // サイト管理のデフォルトマッピングを初期値として使用
    $saved_mapping_json = $session_data['field_mapping'] ?? null;
    if ($saved_mapping_json) {
        $saved_mapping = json_decode($saved_mapping_json, true);
    } else {
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

    $source_options = [
        'blank'    => get_string('source_blank',    'block_dbm_record_provisioner'),
        'fullname' => get_string('source_fullname',  'block_dbm_record_provisioner'),
        'profile'  => get_string('source_profile',   'block_dbm_record_provisioner'),
        'group'    => get_string('source_group',     'block_dbm_record_provisioner'),
        'csv_col'  => get_string('source_csv_col',   'block_dbm_record_provisioner'),
    ];

    $profile_fields   = $DB->get_records('user_info_field', null, 'name ASC', 'id, name, shortname');
    $course_groupings = []; // 管理者一括処理ではグルーピング指定不可

    // enabled_user_fieldsをprofile選択肢に反映
    $enabled_user_fields = get_config('block_dbm_record_provisioner', 'enabled_user_fields');
    $enabled_user_fields = $enabled_user_fields ? explode(',', $enabled_user_fields) : ['idnumber'];
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

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_select_map_heading', 'block_dbm_record_provisioner'));

    $actionurl = new moodle_url($PAGE->url, ['step' => 'select_map']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::tag('p', get_string('field_mapping_desc', 'block_dbm_record_provisioner'),
        ['class' => 'text-muted']);

    $table       = new html_table();
    $table->head = [
        get_string('db_field_name',  'block_dbm_record_provisioner'),
        get_string('mapping_source', 'block_dbm_record_provisioner'),
        get_string('mapping_param',  'block_dbm_record_provisioner'),
    ];
    $table->data = [];

    foreach ($fields as $field) {
        $current_source = $saved_mapping[$field->id]['source'] ?? 'blank';
        $current_param  = $saved_mapping[$field->id]['param']  ?? 0;

        $source_select = html_writer::select(
            $source_options,
            "field_map[{$field->id}]",
            $current_source,
            false,
            [
                'class'        => 'custom-select custom-select-sm dbm-source-select',
                'data-fieldid' => $field->id,
                'onchange'     => 'dbmUpdateParam(this)',
            ]
        );

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

        // 管理者一括処理ではグルーピング選択なし（コースをまたぐため）
        $group_select = html_writer::tag('div',
            html_writer::tag('small',
                get_string('admin_group_no_grouping', 'block_dbm_record_provisioner'),
                ['class' => 'text-muted']
            ),
            [
                'class' => 'dbm-param-group' . ($current_source === 'group' ? '' : ' d-none'),
                'id'    => "param_group_{$field->id}",
            ]
        );

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

    // JS：ソース選択連動
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
// STEP: target - 対象者指定（全員 or ロールのみ）
// ================================================================
if ($step === 'target') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode   = required_param('target_mode', PARAM_ALPHA);
        $params = [];
        if ($mode === 'role') {
            $params['roleid'] = required_param('roleid', PARAM_INT);
        }
        $duplicate = optional_param('duplicate', 'skip', PARAM_ALPHA);

        $SESSION->$SESSION_KEY = array_merge($session_data, [
            'target_mode'   => $mode,
            'target_params' => $params,
            'duplicate'     => $duplicate,
        ]);
        redirect(new moodle_url($PAGE->url, ['step' => 'preview']));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_target_heading', 'block_dbm_record_provisioner'));

    $actionurl = new moodle_url($PAGE->url, ['step' => 'target']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl,
        'enctype' => 'multipart/form-data']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    // 対象者モード（全員 or ロール指定のみ）
    $modes = ['all', 'role'];
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

        echo html_writer::start_div('mt-2 ml-4', ['id' => 'options_' . $mode]);
        if ($mode === 'role') {
            // ロール一覧：全コースのロールを集約
            $courses   = dbm_admin_get_courses_in_category($categoryid);
            $all_roles = [];
            foreach ($courses as $course) {
                $resolver = new block_dbm_record_provisioner_target_resolver($course->id);
                foreach ($resolver->get_course_roles() as $role) {
                    $all_roles[$role->id] = $role->localname;
                }
            }
            $roptions = [0 => get_string('select_role', 'block_dbm_record_provisioner')] + $all_roles;
            echo html_writer::select($roptions, 'roleid', 0, false,
                ['class' => 'custom-select custom-select-sm w-auto']);
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // 重複時の動作
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
// STEP: preview - コースごとの確認
// ================================================================
if ($step === 'preview') {

    $selected_dbids = $session_data['selected_dbids'] ?? [];
    $mapping        = json_decode($session_data['field_mapping'] ?? '{}', true);
    $target_mode    = $session_data['target_mode']   ?? 'all';
    $target_params  = $session_data['target_params'] ?? [];
    $duplicate      = $session_data['duplicate']     ?? 'skip';

    if (empty($selected_dbids)) {
        redirect(new moodle_url($PAGE->url, ['step' => 'select_db']));
    }

    // DBとコースの対応を取得
    $db_course_map = [];
    foreach ($selected_dbids as $dbid) {
        $db = $DB->get_record('data', ['id' => $dbid], 'id, name, course');
        if ($db) {
            $db_course_map[$dbid] = $db;
        }
    }

    // コースごとにユーザーを解決してper_db_useridsを組み立てる
    $per_db_userids = [];
    $total_users    = 0;
    foreach ($db_course_map as $dbid => $db) {
        $resolver = new block_dbm_record_provisioner_target_resolver($db->course);
        $userids  = $resolver->resolve($target_mode, $target_params);
        $per_db_userids[$dbid] = $userids;
        $total_users += count($userids);
    }

    $provisioner = new block_dbm_record_provisioner_provisioner(
        $selected_dbids, $mapping, 0, $USER->id
    );
    $preview = $provisioner->preview([], $duplicate, $per_db_userids);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_preview_heading', 'block_dbm_record_provisioner'));

    // 合計サマリー
    echo html_writer::start_div('alert alert-info');
    echo html_writer::tag('p', get_string('preview_will_insert', 'block_dbm_record_provisioner',
        $preview['will_insert']));
    if ($preview['will_skip'] > 0) {
        echo html_writer::tag('p', get_string('preview_will_skip', 'block_dbm_record_provisioner',
            $preview['will_skip']));
    }
    echo html_writer::end_div();

    // コース別明細テーブル
    $table       = new html_table();
    $table->head = [
        get_string('course'),
        get_string('database', 'data'),
        get_string('preview_will_insert', 'block_dbm_record_provisioner', ''),
        get_string('preview_will_skip',   'block_dbm_record_provisioner', ''),
    ];
    $table->data = [];
    foreach ($db_course_map as $dbid => $db) {
        $course  = $DB->get_record('course', ['id' => $db->course], 'fullname');
        $counts  = $preview['per_db'][$dbid] ?? ['will_insert' => 0, 'will_skip' => 0];
        $table->data[] = [
            $course ? format_string($course->fullname) : "ID:{$db->course}",
            format_string($db->name),
            $counts['will_insert'],
            $counts['will_skip'],
        ];
    }
    echo html_writer::table($table);

    // 実行フォーム
    $actionurl = new moodle_url($PAGE->url, ['step' => 'execute']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

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

    $selected_dbids = $session_data['selected_dbids'] ?? [];
    $mapping        = json_decode($session_data['field_mapping'] ?? '{}', true);
    $target_mode    = $session_data['target_mode']   ?? 'all';
    $target_params  = $session_data['target_params'] ?? [];
    $duplicate      = $session_data['duplicate']     ?? 'skip';

    if (empty($selected_dbids)) {
        redirect(new moodle_url($PAGE->url, ['step' => 'select_db']));
    }

    // DBとコースの対応を取得
    $db_course_map = [];
    foreach ($selected_dbids as $dbid) {
        $db = $DB->get_record('data', ['id' => $dbid], 'id, name, course');
        if ($db) {
            $db_course_map[$dbid] = $db;
        }
    }

    // コースごとにユーザーを解決
    $per_db_userids = [];
    foreach ($db_course_map as $dbid => $db) {
        $resolver = new block_dbm_record_provisioner_target_resolver($db->course);
        $per_db_userids[$dbid] = $resolver->resolve($target_mode, $target_params);
    }

    $provisioner = new block_dbm_record_provisioner_provisioner(
        $selected_dbids, $mapping, 0, $USER->id
    );
    $result = $provisioner->provision([], $duplicate, $per_db_userids);

    // セッションクリア
    unset($SESSION->$SESSION_KEY);

    // 完了画面
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('step_complete_heading', 'block_dbm_record_provisioner'));

    // 合計サマリー
    echo html_writer::start_div('alert alert-success');
    echo html_writer::tag('p', get_string('result_inserted', 'block_dbm_record_provisioner',
        $result['inserted']));
    if ($result['skipped'] > 0) {
        echo html_writer::tag('p', get_string('result_skipped', 'block_dbm_record_provisioner',
            $result['skipped']));
    }
    echo html_writer::end_div();

    // コース別明細テーブル
    $table       = new html_table();
    $table->head = [
        get_string('course'),
        get_string('database', 'data'),
        get_string('result_inserted', 'block_dbm_record_provisioner', ''),
        get_string('result_skipped',  'block_dbm_record_provisioner', ''),
    ];
    $table->data = [];
    foreach ($db_course_map as $dbid => $db) {
        $course = $DB->get_record('course', ['id' => $db->course], 'fullname');
        $counts = $result['per_db'][$dbid] ?? ['inserted' => 0, 'skipped' => 0];
        $table->data[] = [
            $course ? format_string($course->fullname) : "ID:{$db->course}",
            format_string($db->name),
            $counts['inserted'],
            $counts['skipped'],
        ];
    }
    echo html_writer::table($table);

    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'blocksettingdbm_record_provisioner']),
        get_string('backtoadmin', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary mt-3']
    );

    echo $OUTPUT->footer();
    exit;
}
