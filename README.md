# block_dbm_record_provisioner

Moodle block plugin to bulk-provision empty records into Database activities (mod_data).

**日本語の説明は[こちら](#日本語)。**

---

## Overview

When using Moodle's Database activity for peer assessment or structured data collection, instructors often need to pre-populate empty records (with name, ID number, group, etc.) so that evaluators can search and fill them in — rather than creating entries from scratch.

Traditionally this required an administrator to manually prepare and import a CSV file for each Database activity. This block lets teachers and administrative staff do it themselves in a few clicks.

### Key features

- Step-by-step wizard: DB selection → field mapping → target users → confirm → execute
- Multiple Database activities can be provisioned at once (identical field structure required)
- Flexible target user selection: all enrolled users, by role, cohort, profile field, CSV upload, or group
- Field mapping UI with JavaScript-driven parameter switching (profile field / grouping selector)
- Site-wide default mapping configurable by administrators (field name exact match)
- Duplicate handling: skip or add (overwrite is never allowed)
- Safe deletion: only the last provisioned batch, and only if no data has been entered yet
- Admin bulk provisioning tool: category-based cross-course batch generation
- Connects to `block_data_groupassign` for post-provisioning group assignment

---

## Requirements

| Item | Version |
|------|---------|
| Moodle | 4.0 or later |
| PHP | 7.4 or later |

---

## Installation

1. Download or clone this repository into `<moodleroot>/blocks/dbm_record_provisioner/`
2. Log in as an administrator and navigate to **Site administration → Notifications**
3. Follow the on-screen upgrade steps

---

## Usage

### For teachers and administrative staff

1. Add the **DBM Record Provisioner** block to a course page (editing mode)
2. Click **Provision records** to open the wizard
3. **Step 1**: Select one or more Database activities (field structures must match)
4. **Step 2**: Configure field mapping — choose which Moodle data source maps to each DB field
5. **Step 3**: Specify target users (all enrolled / by role / cohort / profile field / CSV / group)
6. **Step 4**: Review the record count per DB, then execute
7. Optionally, click **Assign groups →** to hand off to `block_data_groupassign`

Mapping settings are saved per block instance — no need to reconfigure on subsequent runs.

### For administrators (bulk provisioning)

1. Go to **Site administration → Plugins → Blocks → DBM Record Provisioner**
2. Click **Open bulk provisioning tool →**
3. Select a category (subcategories are included automatically)
4. Select the target Database modules (displayed as "DB name (course name)")
5. Confirm field mapping, select target users (all enrolled or by role), and execute
6. Results are shown per course

---

## Field mapping sources

| Source | Description |
|--------|-------------|
| `(Blank)` | Leave the field empty |
| `Full name` | `lastname + firstname` from `mdl_user` |
| `User profile field` | Any enabled `mdl_user` field (e.g. `idnumber`) or custom profile field |
| `Group name` | The user's group name; optionally filtered by grouping |
| `CSV column` | Value pre-parsed from an uploaded CSV |

Enabled `mdl_user` fields (e.g. `idnumber`, `email`, `department`) are configurable in site administration.

---

## Deletion

The block stores the record ID range of the last provisioned batch in `configdata`. The **Delete last batch** button removes records from that range — but only if **no data has been entered into any record in the batch**. If even one record has been filled in, the entire batch is protected.

For finer-grained deletion, use the Database module's built-in entry management.

---

## Permissions

| Capability | Default roles |
|---|---|
| `block/dbm_record_provisioner:provision` | Manager, Editing teacher |
| `block/dbm_record_provisioner:addinstance` | Manager, Editing teacher |

Admin bulk provisioning requires `moodle/site:config`.

---

## File structure

```
block_dbm_record_provisioner/
├── block_dbm_record_provisioner.php   # Block definition and display
├── edit_form.php                       # Block instance settings (minimal)
├── provision.php                       # Main wizard (teacher/staff flow)
├── provision_admin.php                 # Admin bulk provisioning flow
├── delete.php                          # Batch deletion
├── settings.php                        # Site administration settings
├── version.php
├── db/
│   └── access.php                      # Capability definitions
├── lang/
│   ├── en/block_dbm_record_provisioner.php
│   └── ja/block_dbm_record_provisioner.php
└── classes/
    ├── provisioner.php                 # Core record generation logic
    └── target_resolver.php             # Target user resolution (6 modes)
```

---

## Related plugin

**[block_data_groupassign](https://github.com/yasada0819/moodle-block_data_groupassign)** — assigns group IDs to unassigned Database entries. Designed to run after this plugin provisions the empty records.

---

## Notes & limitations

- Field mapping is saved per block instance; the admin bulk flow uses session storage (configure each time).
- The admin bulk tool supports "all enrolled" and "by role" only — CSV and cohort are excluded to avoid provisioning users not enrolled in the target course.
- `resolve_field_value()` currently queries the DB once per user per field. Performance on very large cohorts (hundreds of users) has not been benchmarked.
- Moodle 4.x is the primary target. Earlier versions are untested.

---

## License

GNU GPL v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).

---
---

## 日本語

### 概要

Moodleのデータベースモジュール（mod_data）に空レコードを一括生成するコースブロックプラグインです。

他者評価などの用途で「氏名・学籍番号・班番号だけ入れた空の評価シート」をあらかじめ作っておき、評価者がそこから検索・入力する運用を、CSVの手動作成なしに実現します。

### 主な機能

- ステップ形式のウィザード（DB選択 → マッピング → 対象者 → 確認 → 実行）
- 複数DBへの同時生成（フィールド構成が一致している場合）
- 対象者指定の6パターン：コース登録者全員・ロール指定・コーホート・プロファイルフィールド・CSVアップロード・グループ
- JSによるパラメータ欄の切り替え（プロファイルフィールド選択 / グルーピング選択）
- サイト管理でデフォルトマッピングを設定可能（フィールド名の完全一致で自動適用）
- 重複時の動作選択（スキップ or 追加）。上書きは絶対なし
- 安全な削除：直近バッチのみ・未入力の場合のみ削除可能
- 管理者向けカテゴリ一括生成ツール（`provision_admin.php`）
- `block_data_groupassign` との連携（生成後にグループIDを付与）

### 動作要件

| 項目 | バージョン |
|------|-----------|
| Moodle | 4.0以上 |
| PHP | 7.4以上 |

### インストール

1. このリポジトリを `<Moodleルート>/blocks/dbm_record_provisioner/` に配置
2. 管理者でログインし、**サイト管理 → 通知** を開く
3. 画面の指示に従ってインストール

### 使い方（教員・事務方）

1. コースの編集モードで **DBM レコードプロビジョナー** ブロックを追加
2. **レコードを生成する** をクリックしてウィザードを起動
3. ステップ1：データベースモジュールを選択（複数可・フィールド構成一致が必要）
4. ステップ2：フィールドマッピングを設定（どのMoodle情報をどのフィールドに渡すか）
5. ステップ3：対象者を指定
6. ステップ4：DB別件数を確認して実行
7. 必要に応じて **グループを割り当てる →** で `block_data_groupassign` に引き渡し

マッピング設定はブロックインスタンスに保存されるため、2回目以降は再設定不要です。

### 使い方（管理者一括生成）

1. **サイト管理 → プラグイン → ブロック → DBM Record Provisioner** を開く
2. **一括生成ツールを開く →** をクリック
3. カテゴリを選択（サブカテゴリも自動収集）
4. 対象DBモジュールを選択（「DB名（コース名）」形式で一覧表示）
5. マッピング確認 → 対象者指定（全員 or ロール指定）→ 実行
6. 結果はコース別の明細テーブルで表示

### 削除について

直近生成バッチのrecord_id範囲をconfigdataに保存し、「直近バッチを削除する」ボタンで削除できます。ただし、**バッチ内に1件でも入力済みのレコードがある場合は削除不可**（バッチ単位で保護）。個別削除が必要な場合はデータベースモジュールのエントリ管理を使用してください。

### 関連プラグイン

**[block_data_groupassign](https://github.com/yasada0819/moodle-block_data_groupassign)** — 生成した空レコードにグループIDを付与するプラグインです。本プラグインで空レコードを生成した後、連携して使用します。

### 備考・制限事項

- マッピング設定はブロックインスタンスごとに保存。管理者一括フローはセッション保存（都度設定）です。
- 管理者一括ツールの対象者指定はコース登録者全員・ロール指定のみ（CSV・コーホートは対象外コースへの混入防止のため非対応）。
- フィールド値の解決は現在1件ずつDBアクセスする実装です。大規模コースでのパフォーマンスは未検証です。
