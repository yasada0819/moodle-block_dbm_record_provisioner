<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

// ---- General ----
$string['pluginname']    = 'DBM レコードプロビジョナー';
$string['blocksettings'] = 'ブロック設定';

// ---- Block display ----
$string['nodatabases']             = 'このコースにデータベースモジュールが見つかりません。';
$string['mapping_not_configured']  = 'フィールドマッピングが設定されていません。プロビジョナーを起動して設定してください。';
$string['provision_button']        = 'レコードを生成する';
$string['delete_button']           = '直近バッチを削除する';

// ---- Capabilities ----
$string['dbm_record_provisioner:provision']   = 'レコードの生成・削除';
$string['dbm_record_provisioner:addinstance'] = 'DBM レコードプロビジョナーブロックを追加';

// ---- Config (edit_form) ----
$string['config_title']      = 'ブロックタイトル（任意）';
$string['config_title_help'] = '空白のままにするとデフォルトのタイトルが使用されます。';

// ---- Step headings ----
$string['step_select_db_heading']  = 'ステップ 1：データベースモジュールを選択';
$string['step_select_map_heading'] = 'ステップ 2：フィールドマッピングを設定';
$string['step_select_heading']     = 'ステップ 1：データベース選択とフィールドマッピング設定'; // legacy
$string['step_target_heading']   = 'ステップ 3：対象者を指定する';
$string['step_preview_heading']  = 'ステップ 4：確認';
$string['step_complete_heading'] = 'レコードの生成が完了しました';

// ---- Step: select ----
$string['select_databases']      = 'データベースモジュールを選択';
$string['select_db_desc']         = '対象のデータベースモジュールを選択してください。複数選択する場合は、フィールド構成（名前と型）が完全に一致している必要があります。';
$string['selected_databases']     = '対象データベース：';
$string['field_mapping_heading'] = 'フィールドマッピング';
$string['field_mapping_desc']    = '各データベースフィールドに、どのMoodle情報を渡すかを選択してください。';
$string['db_field_name']         = 'データベースフィールド';
$string['mapping_source']        = 'データソース';
$string['mapping_param']         = 'パラメータ';

$string['source_blank']    = '（空白）';
$string['source_fullname'] = '氏名（姓＋名）';
$string['source_idnumber'] = '学籍番号（idnumber）'; // プロファイル選択肢として使用
$string['source_profile']  = 'ユーザープロファイルフィールド';
$string['source_group']    = 'グループ名';
$string['source_csv_col']  = 'CSV列';

$string['select_profile_field'] = '-- 選択 --';
$string['no_grouping']          = '（グルーピング指定なし）';

// ---- Step: target ----
$string['target_mode_all']     = 'コース登録者全員（ロール問わず）';
$string['target_mode_cohort']  = 'コーホート指定';
$string['target_mode_profile'] = 'プロファイルフィールド値で絞り込み';
$string['target_mode_csv']     = 'CSVアップロード（{$a} リスト）';
$string['target_mode_group']   = 'グループ指定';

$string['target_mode_role']          = 'ロール指定';
$string['select_role']               = '-- ロールを選択 --';
$string['select_cohort']             = '-- コーホートを選択 --';
$string['select_group']              = '-- グループを選択 --';
$string['profile_value_placeholder'] = '一致する値を入力';
$string['csv_upload_desc']           = '{$a} を1行1件で記載したCSVファイルをアップロードしてください（ヘッダー行は省略可）。';

$string['duplicate_handling'] = '同一ユーザーの空レコードが既にある場合…';
$string['duplicate_skip']     = 'スキップする（重複して追加しない）';
$string['duplicate_add']      = '追加する（空レコードの複数作成を許可）';

// ---- Step: preview ----
$string['preview_will_insert'] = '{$a} 件のレコードを生成します。';
$string['preview_will_skip']   = '{$a} 件はスキップされます（空レコードが既に存在）。';
$string['preview_button']      = '確認';

// ---- Step: execute ----
$string['execute_button']  = '実行する';
$string['result_inserted'] = '{$a} 件のレコードを生成しました。';
$string['result_skipped']  = '{$a} 件をスキップしました。';
$string['goto_groupassign'] = 'グループを割り当てる →';
$string['backtocourse']     = 'コースに戻る';

// ---- Navigation ----
$string['next_button'] = '次へ';
$string['back_button'] = '戻る';

// ---- Delete ----
$string['delete_confirm_heading']       = '直近バッチの削除確認';
$string['delete_confirm_desc']          = '{$a} 件のレコードを完全に削除します。よろしいですか？';
$string['delete_confirm_note']          = '削除対象は直近バッチで生成したレコードのうち、まだ空白のものに限ります。';
$string['delete_execute_button']        = '削除する';
$string['delete_success']               = '{$a} 件のレコードを削除しました。';
$string['delete_nothing']               = '削除対象のレコードが見つかりませんでした。';
$string['delete_no_batch']              = '直近バッチの情報がありません。削除できません。';
$string['delete_blocked_by_input']      = '削除できません：このバッチのレコードに既にデータが入力されています。';
$string['delete_blocked_by_input_desc'] = '個別に削除する場合は、データベースモジュールのエントリ管理から直接行ってください。';

// ---- Errors ----
$string['error_no_db_selected'] = 'データベースモジュールを1つ以上選択してください。';
$string['error_field_mismatch'] = '選択したデータベースのフィールド構成が一致しません。フィールド名と型がすべて同じデータベースを選択してください。';

// ---- Settings ----
$string['settings_userfields_heading']       = 'データソースとして使用するユーザーフィールド';
$string['settings_userfields_desc']          = 'フィールドマッピングのデータソースとして使用できる mdl_user フィールドを選択してください。';
$string['settings_enabled_user_fields']      = '有効にするユーザーフィールド';
$string['settings_enabled_user_fields_desc'] = 'ここで有効にしたフィールドが、マッピング画面のプロファイル選択肢に表示されます。';
$string['settings_mapping_heading']          = 'デフォルトフィールドマッピング';
$string['settings_mapping_desc']             = 'データベースフィールド名（完全一致）でデフォルトのマッピングを定義します。ブロックインスタンスにマッピングが未設定の場合に適用されます。';
$string['settings_mapping_row']              = 'マッピング {$a}';
$string['settings_mapping_fieldname']        = 'フィールド名（完全一致）';
$string['settings_mapping_fieldname_desc']   = 'データベースフィールドの名前を正確に入力してください。';
$string['settings_mapping_source']           = 'デフォルトのソース';
$string['settings_mapping_nosource']         = '-- 未設定 --';
$string['csv_not_found_warning'] = 'CSVの {$a} 件のIDがMoodleユーザーに見つかりませんでした（スキップされます）：';

// ---- Admin bulk provision ----
$string['bulk_provision_heading']           = '一括レコード生成（管理者）';
$string['bulk_provision_link']              = '一括生成ツールを開く →';
$string['admin_step_select_category']       = 'ステップ 1：カテゴリを選択';
$string['admin_select_category_desc']       = 'カテゴリを選択してください。そのカテゴリ以下（サブカテゴリ含む）のコースにあるデータベースモジュールが一覧表示されます。';
$string['select_category']                  = '-- カテゴリを選択 --';
$string['admin_selected_category']          = '対象カテゴリ：';
$string['admin_step_select_db']             = 'ステップ 2：データベースモジュールを選択';
$string['admin_select_db_desc']             = '生成対象のデータベースモジュールを選択してください。選択したすべてのデータベースのフィールド構成が一致している必要があります。';
$string['admin_no_databases_in_category']   = 'このカテゴリのコースにデータベースモジュールが見つかりませんでした。';
$string['admin_group_no_grouping']          = '（一括モードではグルーピング指定は利用できません）';
$string['backtoadmin']                      = '設定ページに戻る';
$string['error_no_category_selected']       = 'カテゴリを選択してください。';

