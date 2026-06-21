<?php
// This file is part of Moodle - http://moodle.org/
require_once('../../config.php');

$courseid   = required_param('courseid',   PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$mode       = optional_param('mode', 'confirm', PARAM_ALPHA); // confirm | execute

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
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

$PAGE->set_url('/blocks/dbm_record_provisioner/delete.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('pluginname', 'block_dbm_record_provisioner'));
$PAGE->set_heading($course->fullname);

// 直近バッチ情報の確認
$from = (int)($config->last_provisioned_from ?? 0);
$to   = (int)($config->last_provisioned_to   ?? 0);

if ($from <= 0 || $to <= 0) {
    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('delete_no_batch', 'block_dbm_record_provisioner'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$dataids = array_map('intval', explode(',', $config->selected_dataids ?? ''));

// ================================================================
// 削除対象レコードの取得
// 条件：
//   - dataid が対象DBのいずれか
//   - record_id が last_provisioned_from 〜 last_provisioned_to の範囲内
//   - バッチ内の全レコードが空白（1件でも入力済みがあればバッチ丸ごと削除不可）
//
// @return array|null  削除可能なレコード配列、削除不可の場合は null
// ================================================================
function dbm_provisioner_get_deletable_records(array $dataids, int $from, int $to, stdClass $config): ?array {
    global $DB;

    if (empty($dataids) || $from <= 0 || $to <= 0) {
        return null;
    }

    list($in_sql, $in_params) = $DB->get_in_or_equal($dataids, SQL_PARAMS_NAMED, 'did');
    $in_params['from'] = $from;
    $in_params['to']   = $to;

    // 範囲内・対象DBのレコードを全件取得
    $sql = "SELECT r.id, r.userid, r.dataid
              FROM {data_records} r
             WHERE r.dataid $in_sql
               AND r.id >= :from
               AND r.id <= :to";

    $records = $DB->get_records_sql($sql, $in_params);

    if (empty($records)) {
        return [];
    }

    // マッピングで値を入れたフィールドIDを取得（これらは最初から値があって当然なので除外）
    $mapping = json_decode($config->field_mapping ?? '{}', true);
    $provisioned_fieldids = [];
    foreach ($mapping as $fieldid => $map) {
        if (($map['source'] ?? 'blank') !== 'blank') {
            $provisioned_fieldids[] = (int)$fieldid;
        }
    }

    // バッチ内に1件でも「マッピング外フィールドに入力済み」があれば丸ごと削除不可（null を返す）
    foreach ($records as $rec) {
        $contents = $DB->get_records('data_content', ['recordid' => $rec->id], '', 'fieldid, content');
        foreach ($contents as $c) {
            // マッピングで値を入れたフィールドはスキップ
            if (in_array((int)$c->fieldid, $provisioned_fieldids)) {
                continue;
            }
            if ($c->content !== null && $c->content !== '') {
                return null; // 教員が入力済み → 削除不可
            }
        }
    }

    return array_values($records);
}

$deletable = dbm_provisioner_get_deletable_records($dataids, $from, $to, $config);

// ================================================================
// EXECUTE - 削除実行
// ================================================================
if ($mode === 'execute') {

    // 削除不可（入力済みあり）
    if ($deletable === null) {
        redirect(
            new moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('delete_blocked_by_input', 'block_dbm_record_provisioner'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if (empty($deletable)) {
        redirect(
            new moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('delete_nothing', 'block_dbm_record_provisioner'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    $deleted = 0;
    foreach ($deletable as $rec) {
        // mdl_data_content を先に削除
        $DB->delete_records('data_content', ['recordid' => $rec->id]);
        // mdl_data_records を削除
        $DB->delete_records('data_records', ['id' => $rec->id]);
        $deleted++;
    }

    // configdata のバッチ情報をリセット
    $config->last_provisioned_from = 0;
    $config->last_provisioned_to   = 0;
    $instance->configdata = base64_encode(serialize($config));
    $DB->update_record('block_instances', $instance);

    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('delete_success', 'block_dbm_record_provisioner', $deleted),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ================================================================
// CONFIRM - 確認画面
// ================================================================
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('delete_confirm_heading', 'block_dbm_record_provisioner'));

// 入力済みレコードありで削除不可
if ($deletable === null) {
    echo $OUTPUT->notification(
        get_string('delete_blocked_by_input', 'block_dbm_record_provisioner'),
        \core\output\notification::NOTIFY_ERROR
    );
    echo html_writer::tag('p',
        get_string('delete_blocked_by_input_desc', 'block_dbm_record_provisioner'),
        ['class' => 'text-muted small']
    );
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('backtocourse', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary mt-2']
    );
    echo $OUTPUT->footer();
    exit;
}

if (empty($deletable)) {
    // 削除対象なし
    echo $OUTPUT->notification(
        get_string('delete_nothing', 'block_dbm_record_provisioner'),
        \core\output\notification::NOTIFY_INFO
    );
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('backtocourse', 'block_dbm_record_provisioner'),
        ['class' => 'btn btn-secondary mt-2']
    );
    echo $OUTPUT->footer();
    exit;
}

// 削除対象の件数・ユーザー一覧を表示
echo html_writer::start_div('alert alert-warning');
echo html_writer::tag('p',
    get_string('delete_confirm_desc', 'block_dbm_record_provisioner', count($deletable)));
echo html_writer::tag('p',
    get_string('delete_confirm_note', 'block_dbm_record_provisioner'),
    ['class' => 'mb-0 small']);
echo html_writer::end_div();

// 削除対象ユーザー一覧テーブル
$rows = [];
foreach ($deletable as $rec) {
    $user = $DB->get_record('user', ['id' => $rec->userid], 'id, firstname, lastname, idnumber');
    if (!$user) continue;
    $db   = $DB->get_record('data', ['id' => $rec->dataid], 'name');
    $rows[] = [
        $user->idnumber,
        fullname($user),
        $db ? format_string($db->name) : "ID:{$rec->dataid}",
    ];
}

$table = new html_table();
$table->head = [
    get_string('idnumber'),
    get_string('fullname'),
    get_string('database', 'data'),
];
$table->data = $rows;
echo html_writer::table($table);

// 実行・キャンセルボタン
$actionurl = new moodle_url($PAGE->url, ['mode' => 'execute']);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mt-3');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('delete_execute_button', 'block_dbm_record_provisioner'),
    'class' => 'btn btn-danger mr-2',
]);
echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('cancel'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
