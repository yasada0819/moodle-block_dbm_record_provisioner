<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

/**
 * 対象者解決クラス
 *
 * 5パターンの指定方法から、空レコード生成対象のユーザーIDリストを返す。
 * provisioner.php から呼び出して使う。
 *
 * 解決パターン：
 *   - all      : コース受講者全員
 *   - role     : ロール指定
 *   - cohort   : コーホート指定
 *   - profile  : プロファイルフィールド値で絞り込み
 *   - csv      : CSVアップロード（idnumberリスト）
 *   - group    : グループ指定
 */
class block_dbm_record_provisioner_target_resolver {

    /** @var int コースID */
    private $courseid;

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * 対象ユーザーIDリストを返す
     *
     * @param string $mode  解決パターン（all / cohort / profile / csv / group）
     * @param array  $params モードごとのパラメータ
     *   - cohort  : ['cohortid' => int]
     *   - profile : ['fieldid' => int, 'value' => string]
     *   - csv     : ['userids' => int[]]  ※呼び出し元でCSVパース済みのIDリストを渡す
     *   - group   : ['groupid' => int]
     * @return int[] ユーザーIDの配列（重複なし）
     */
    public function resolve(string $mode, array $params = []): array {
        switch ($mode) {
            case 'all':
                return $this->resolve_all();
            case 'role':
                return $this->resolve_role($params['roleid'] ?? 0);
            case 'cohort':
                return $this->resolve_cohort($params['cohortid'] ?? 0);
            case 'profile':
                return $this->resolve_profile($params['fieldid'] ?? 0, $params['value'] ?? '');
            case 'csv':
                return $this->resolve_csv($params['userids'] ?? []);
            case 'group':
                return $this->resolve_group($params['groupid'] ?? 0);
            default:
                return [];
        }
    }

    // ----------------------------------------------------------------
    // パターン①：コース受講者全員
    // ----------------------------------------------------------------
    private function resolve_all(): array {
        global $DB;

        $context = context_course::instance($this->courseid);
        $users   = get_enrolled_users($context, '', 0, 'u.id');

        return array_keys($users);
    }

    // ----------------------------------------------------------------
    // パターン②：ロール指定
    // ----------------------------------------------------------------
    private function resolve_role(int $roleid): array {
        global $DB;

        if ($roleid <= 0) {
            return [];
        }

        $context = context_course::instance($this->courseid);

        // 指定ロールかつコース受講済みのユーザーに絞る
        $sql = "SELECT DISTINCT u.id
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                 WHERE ra.roleid  = :roleid
                   AND ra.contextid = :contextid
                   AND u.deleted = 0";

        $records = $DB->get_records_sql($sql, [
            'courseid'  => $this->courseid,
            'roleid'    => $roleid,
            'contextid' => $context->id,
        ]);

        return array_keys($records);
    }

    // ----------------------------------------------------------------
    // パターン③：コーホート指定
    // ----------------------------------------------------------------
    private function resolve_cohort(int $cohortid): array {
        global $DB;

        if ($cohortid <= 0) {
            return [];
        }

        // コーホートメンバーのうち、このコースに受講登録されているユーザーに絞る
        $context = context_course::instance($this->courseid);
        $enrolled = get_enrolled_users($context, '', 0, 'u.id');

        if (empty($enrolled)) {
            return [];
        }

        list($in_sql, $in_params) = $DB->get_in_or_equal(array_keys($enrolled), SQL_PARAMS_NAMED, 'uid');
        $in_params['cohortid'] = $cohortid;

        $sql = "SELECT cm.userid
                  FROM {cohort_members} cm
                 WHERE cm.cohortid = :cohortid
                   AND cm.userid $in_sql";

        $records = $DB->get_records_sql($sql, $in_params);
        return array_keys($records);
    }

    // ----------------------------------------------------------------
    // パターン③：プロファイルフィールド値で絞り込み
    // ----------------------------------------------------------------
    private function resolve_profile(int $fieldid, string $value): array {
        global $DB;

        if ($fieldid <= 0 || $value === '') {
            return [];
        }

        $context  = context_course::instance($this->courseid);
        $enrolled = get_enrolled_users($context, '', 0, 'u.id');

        if (empty($enrolled)) {
            return [];
        }

        list($in_sql, $in_params) = $DB->get_in_or_equal(array_keys($enrolled), SQL_PARAMS_NAMED, 'uid');
        $in_params['fieldid'] = $fieldid;
        $in_params['value']   = $value;

        $sql = "SELECT uid.userid
                  FROM {user_info_data} uid
                 WHERE uid.fieldid = :fieldid
                   AND uid.data    = :value
                   AND uid.userid $in_sql";

        $records = $DB->get_records_sql($sql, $in_params);
        return array_keys($records);
    }

    // ----------------------------------------------------------------
    // パターン④：CSVアップロード（学籍番号リスト）
    // ----------------------------------------------------------------
    private function resolve_csv(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        // コース受講者に絞る
        $context  = context_course::instance($this->courseid);
        $enrolled = get_enrolled_users($context, '', 0, 'u.id');

        if (empty($enrolled)) {
            return [];
        }

        // CSVのIDリストと受講者リストの積集合
        $enrolled_ids = array_keys($enrolled);
        $filtered     = array_intersect($userids, $enrolled_ids);

        return array_values(array_unique($filtered));
    }

    // ----------------------------------------------------------------
    // パターン⑤：グループ指定
    // ----------------------------------------------------------------
    private function resolve_group(int $groupid): array {
        global $DB;

        if ($groupid <= 0) {
            return [];
        }

        $members = groups_get_members($groupid, 'u.id');
        return array_keys($members);
    }

    // ----------------------------------------------------------------
    // ユーティリティ：コース内のコーホート一覧を返す（UI用）
    // ----------------------------------------------------------------
    public function get_course_cohorts(): array {
        global $DB;

        $context = context_course::instance($this->courseid);

        $sql = "SELECT DISTINCT c.id, c.name
                  FROM {cohort} c
                  JOIN {cohort_members} cm ON cm.cohortid = c.id
                  JOIN {user_enrolments} ue ON ue.userid = cm.userid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                 ORDER BY c.name ASC";

        return $DB->get_records_sql($sql, ['courseid' => $this->courseid]);
    }

    // ----------------------------------------------------------------
    // ユーティリティ：コース内のカスタムプロファイルフィールド一覧（UI用）
    // ----------------------------------------------------------------
    public function get_profile_fields(): array {
        global $DB;
        return $DB->get_records('user_info_field', null, 'name ASC', 'id, name, shortname');
    }

    // ----------------------------------------------------------------
    // ユーティリティ：コース内のグループ一覧（UI用）
    // ----------------------------------------------------------------
    public function get_course_groups(): array {
        return groups_get_all_groups($this->courseid);
    }

    // ----------------------------------------------------------------
    // ユーティリティ：コースで使われているロール一覧（UI用）
    // ----------------------------------------------------------------
    public function get_course_roles(): array {
        global $DB;

        $context = context_course::instance($this->courseid);

        $sql = "SELECT DISTINCT r.id, r.name, r.shortname, r.sortorder
                  FROM {role} r
                  JOIN {role_assignments} ra ON ra.roleid = r.id
                 WHERE ra.contextid = :contextid
                 ORDER BY r.sortorder ASC";

        $roles = $DB->get_records_sql($sql, ['contextid' => $context->id]);

        // ロール名はコンテキストで上書きされてる可能性があるのでrole_get_name()で取得
        foreach ($roles as $role) {
            $role->localname = role_get_name($role, $context);
        }

        return $roles;
    }
}
