<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

class block_dbm_record_provisioner extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_dbm_record_provisioner');
    }

    public function applicable_formats() {
        return ['course-view' => true];
    }

    // サイト管理にグローバル設定メニューを表示する
    public function has_config() {
        return true;
    }

    public function instance_allow_config() {
        return true;
    }

    public function get_content() {
        global $DB, $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';

        $context = context_course::instance($COURSE->id);
        if (!has_capability('block/dbm_record_provisioner:provision', $context)) {
            $this->content->text = '';
            return $this->content;
        }

        // コース内のデータベースモジュール一覧
        $databases = $DB->get_records('data', ['course' => $COURSE->id], 'name ASC', 'id, name');
        if (empty($databases)) {
            $this->content->text = html_writer::tag('p',
                get_string('nodatabases', 'block_dbm_record_provisioner'),
                ['class' => 'small text-muted']
            );
            return $this->content;
        }

        $config = $this->config;

        $provision_url = new moodle_url('/blocks/dbm_record_provisioner/provision.php', [
            'courseid'   => $COURSE->id,
            'instanceid' => $this->instance->id,
        ]);
        $delete_url = new moodle_url('/blocks/dbm_record_provisioner/delete.php', [
            'courseid'   => $COURSE->id,
            'instanceid' => $this->instance->id,
        ]);

        $html = '';

        // 空レコード生成ボタン（マッピング設定も含めてprovision.phpで行う）
        $html .= html_writer::div(
            html_writer::link(
                $provision_url,
                get_string('provision_button', 'block_dbm_record_provisioner'),
                ['class' => 'btn btn-primary btn-sm w-100 mb-2']
            )
        );

        // 削除ボタン（直近バッチが存在する場合のみ有効）
        $has_last_batch = !empty($config->last_provisioned_from) && !empty($config->last_provisioned_to);
        $html .= html_writer::div(
            html_writer::link(
                $delete_url,
                get_string('delete_button', 'block_dbm_record_provisioner'),
                ['class' => 'btn btn-outline-danger btn-sm w-100' . ($has_last_batch ? '' : ' disabled')]
            )
        );

        $this->content->text = $html;
        return $this->content;
    }
}
