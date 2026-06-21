<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

/**
 * 空レコード生成クラス
 *
 * mdl_data_records + mdl_data_content に空レコードを一括INSERT する。
 * 上書きは絶対なし。重複時は「新規追加」か「スキップ」を選択できる。
 *
 * 生成したバッチの record_id 範囲は呼び出し元（provision.php）が
 * configdata に保存する。
 */
class block_dbm_record_provisioner_provisioner {

    /** @var int[] 対象DBモジュールIDの配列 */
    private $dataids;

    /** @var array フィールドマッピング設定
     *  [ 'db_field_id' => ['source' => 'idnumber'|'fullname'|'profile'|'group'|'csv_col'|'blank',
     *                      'param'  => mixed] ]
     */
    private $mapping;

    /** @var int コースID */
    private $courseid;

    /** @var int 操作ユーザーID */
    private $operatorid;

    public function __construct(array $dataids, array $mapping, int $courseid, int $operatorid) {
        $this->dataids    = $dataids;
        $this->mapping    = $mapping;
        $this->courseid   = $courseid;
        $this->operatorid = $operatorid;
    }

    // ----------------------------------------------------------------
    // メイン：空レコード生成
    //
    // @param int[]  $userids    対象ユーザーIDリスト（target_resolverが返したもの）
    // @param string $duplicate  重複時の動作: 'add'=新規追加, 'skip'=スキップ
    // @return array ['inserted' => int, 'skipped' => int,
    //               'min_record_id' => int, 'max_record_id' => int]
    // ----------------------------------------------------------------
    public function provision(array $userids, string $duplicate = 'skip', array $per_db_userids = []): array {
        global $DB;

        if ((empty($userids) && empty($per_db_userids)) || empty($this->dataids)) {
            return ['inserted' => 0, 'skipped' => 0, 'min_record_id' => 0, 'max_record_id' => 0, 'per_db' => []];
        }

        $inserted       = 0;
        $skipped        = 0;
        $all_record_ids = [];
        $per_db         = [];

        foreach ($this->dataids as $dataid) {
            // DB別ユーザーリストがあればそちらを優先
            $targets = !empty($per_db_userids[$dataid]) ? $per_db_userids[$dataid] : $userids;

            // このDBのフィールド定義を取得
            $fields = $DB->get_records('data_fields', ['dataid' => $dataid], 'id ASC');

            $db_inserted = 0;
            $db_skipped  = 0;

            foreach ($targets as $userid) {

                // 重複チェック（スキップモードのみ）
                if ($duplicate === 'skip') {
                    $existing = $this->find_empty_record($dataid, $userid);
                    if ($existing) {
                        $db_skipped++;
                        continue;
                    }
                }

                // mdl_data_records に1行INSERT
                $record             = new stdClass();
                $record->userid     = $userid;
                $record->dataid     = $dataid;
                $record->groupid    = 0;
                $record->timecreated  = time();
                $record->timemodified = time();
                $record->approved   = 1; // 承認済み状態で生成

                $record_id = $DB->insert_record('data_records', $record);
                $all_record_ids[] = $record_id;

                // mdl_data_content に各フィールドの値をINSERT
                foreach ($fields as $field) {
                    $content             = new stdClass();
                    $content->fieldid    = $field->id;
                    $content->recordid   = $record_id;
                    $content->content    = $this->resolve_field_value($field, $userid);
                    $content->content1   = null;
                    $content->content2   = null;
                    $content->content3   = null;
                    $content->content4   = null;
                    $DB->insert_record('data_content', $content);
                }

                $db_inserted++;
            }

            $inserted          += $db_inserted;
            $skipped           += $db_skipped;
            $per_db[$dataid]    = ['inserted' => $db_inserted, 'skipped' => $db_skipped];
        }

        return [
            'inserted'       => $inserted,
            'skipped'        => $skipped,
            'min_record_id'  => !empty($all_record_ids) ? min($all_record_ids) : 0,
            'max_record_id'  => !empty($all_record_ids) ? max($all_record_ids) : 0,
            'per_db'         => $per_db,
        ];
    }

    // ----------------------------------------------------------------
    // フィールド値の解決
    // マッピング設定に従って、各フィールドに入れる値を返す。
    // ----------------------------------------------------------------
    private function resolve_field_value(stdClass $field, int $userid): string {
        global $DB;

        // このフィールドのマッピング設定を取得
        $map = $this->mapping[$field->id] ?? null;

        if (!$map || $map['source'] === 'blank') {
            return '';
        }

        switch ($map['source']) {

            case 'fullname':
                // 氏名（姓＋名）
                $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
                return $user ? $user->lastname . $user->firstname : '';

            case 'profile':
                // プロファイルフィールド or idnumber
                // param が 'idnumber' のときは mdl_user.idnumber を返す
                // param が 数値のときはカスタムプロファイルフィールドを返す
                $param = $map['param'] ?? 0;
                if ($param === 'idnumber' || $param === 0 && ($map['param_type'] ?? '') === 'idnumber') {
                    $user = $DB->get_record('user', ['id' => $userid], 'idnumber');
                    return $user ? $user->idnumber : '';
                }
                $fieldid = (int)$param;
                if (!$fieldid) return '';
                $data = $DB->get_record('user_info_data',
                    ['userid' => $userid, 'fieldid' => $fieldid], 'data');
                return $data ? $data->data : '';

            case 'group':
                // グループ名（グルーピングIDで絞り込む。0=絞り込みなし）
                $groupingid = (int)($map['grouping'] ?? 0);
                $groups     = groups_get_user_groups($this->courseid, $userid);
                if ($groupingid > 0) {
                    $groupids = isset($groups[$groupingid]) ? array_values($groups[$groupingid]) : [];
                } else {
                    // グルーピング指定なし → 全グループのうち最初の1件
                    $groupids = isset($groups[0]) ? array_values($groups[0]) : [];
                }
                if (empty($groupids)) return '';
                $group = groups_get_group($groupids[0]);
                return $group ? $group->name : '';

            case 'csv_col':
                // CSV列から事前にパースした値（provision.phpがmappingに埋め込む）
                return $map['param'][$userid] ?? '';

            default:
                return '';
        }
    }

    // ----------------------------------------------------------------
    // 空レコードの存在確認
    // 同一ユーザーの「全フィールドが空」のレコードが存在するか調べる。
    // ----------------------------------------------------------------
    private function find_empty_record(int $dataid, int $userid): bool {
        global $DB;

        $records = $DB->get_records('data_records',
            ['dataid' => $dataid, 'userid' => $userid], 'id ASC', 'id');

        if (empty($records)) {
            return false;
        }

        foreach ($records as $rec) {
            // このレコードの全contentが空かチェック
            $contents = $DB->get_records('data_content', ['recordid' => $rec->id], '', 'content');
            $all_empty = true;
            foreach ($contents as $c) {
                if ($c->content !== null && $c->content !== '') {
                    $all_empty = false;
                    break;
                }
            }
            if ($all_empty) {
                return true; // 空レコードが1件でもあればスキップ
            }
        }

        return false;
    }

    // ----------------------------------------------------------------
    // プレビュー：生成件数の見積もり（実際にはINSERTしない）
    //
    // @param int[]  $userids    単一コースの場合のユーザーIDリスト
    // @param string $duplicate
    // @param array  $per_db_userids  DB別ユーザーIDリスト ['dataid' => [userid, ...]]
    //                                複数コース一括処理時に使用
    // @return array [
    //   'will_insert' => int,
    //   'will_skip'   => int,
    //   'per_db'      => ['dataid' => ['will_insert' => int, 'will_skip' => int]]
    // ]
    // ----------------------------------------------------------------
    public function preview(array $userids, string $duplicate = 'skip', array $per_db_userids = []): array {
        global $DB;

        $will_insert = 0;
        $will_skip   = 0;
        $per_db      = [];

        foreach ($this->dataids as $dataid) {
            // DB別ユーザーリストがあればそちらを優先
            $targets = !empty($per_db_userids[$dataid]) ? $per_db_userids[$dataid] : $userids;

            $db_insert = 0;
            $db_skip   = 0;

            foreach ($targets as $userid) {
                if ($duplicate === 'skip') {
                    $existing = $this->find_empty_record($dataid, $userid);
                    if ($existing) {
                        $db_skip++;
                        continue;
                    }
                }
                $db_insert++;
            }

            $will_insert        += $db_insert;
            $will_skip          += $db_skip;
            $per_db[$dataid]     = ['will_insert' => $db_insert, 'will_skip' => $db_skip];
        }

        return [
            'will_insert' => $will_insert,
            'will_skip'   => $will_skip,
            'per_db'      => $per_db,
        ];
    }

    // ----------------------------------------------------------------
    // フィールド構成の一致チェック（複数DB選択時のバリデーション）
    //
    // @param int[] $dataids
    // @return bool 全DBのフィールド構成（名前＋型）が一致すればtrue
    // ----------------------------------------------------------------
    public static function validate_field_consistency(array $dataids): bool {
        global $DB;

        if (count($dataids) <= 1) {
            return true;
        }

        $signatures = [];
        foreach ($dataids as $dataid) {
            $fields = $DB->get_records('data_fields', ['dataid' => $dataid], 'name ASC', 'name, type');
            // フィールド名+型を文字列化してシグネチャとする
            $sig = implode(',', array_map(
                fn($f) => $f->name . ':' . $f->type,
                $fields
            ));
            $signatures[] = $sig;
        }

        return count(array_unique($signatures)) === 1;
    }
}
