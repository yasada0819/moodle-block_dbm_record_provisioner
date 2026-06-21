<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

// ---- General ----
$string['pluginname']          = 'DBM Record Provisioner';
$string['blocksettings']       = 'Block settings';

// ---- Block display ----
$string['nodatabases']          = 'No database modules found in this course.';
$string['mapping_not_configured'] = 'Field mapping is not configured. Please run the provisioner to set it up.';
$string['provision_button']     = 'Provision records';
$string['delete_button']        = 'Delete last batch';

// ---- Capabilities ----
$string['dbm_record_provisioner:provision']   = 'Provision and delete records';
$string['dbm_record_provisioner:addinstance'] = 'Add a DBM Record Provisioner block';

// ---- Config (edit_form) ----
$string['config_title']      = 'Block title (optional)';
$string['config_title_help'] = 'Leave blank to use the default title.';

// ---- Step headings ----
$string['step_select_db_heading']  = 'Step 1: Select database module(s)';
$string['step_select_map_heading'] = 'Step 2: Configure field mapping';
$string['step_select_heading']     = 'Step 1: Select database and configure field mapping'; // legacy
$string['step_target_heading']  = 'Step 2: Specify target users';
$string['step_preview_heading'] = 'Step 3: Preview';
$string['step_complete_heading'] = 'Records provisioned successfully';

// ---- Step: select ----
$string['select_databases']      = 'Select database module(s)';
$string['select_db_desc']         = 'Select one or more database modules. If you select multiple, they must have identical field configurations.';
$string['selected_databases']     = 'Target database(s):';
$string['field_mapping_heading'] = 'Field mapping';
$string['field_mapping_desc']    = 'For each database field, choose which Moodle data source to use.';
$string['db_field_name']         = 'Database field';
$string['mapping_source']        = 'Data source';
$string['mapping_param']         = 'Parameter';

$string['source_blank']    = '(Blank)';
$string['source_fullname'] = 'Full name (Last + First)';
$string['source_idnumber'] = 'ID number (idnumber)';
$string['source_profile']  = 'User profile field';
$string['source_group']    = 'Group name';
$string['source_csv_col']  = 'CSV column';

$string['select_profile_field'] = '-- Select --';
$string['no_grouping']          = '(No grouping)';

// ---- Step: target ----
$string['target_mode_all']     = 'All enrolled users (regardless of role)';
$string['target_mode_cohort']  = 'Cohort';
$string['target_mode_profile'] = 'User profile field value';
$string['target_mode_csv']     = 'CSV upload ({$a} list)';
$string['target_mode_group']   = 'Group';

$string['target_mode_role']    = 'By role';
$string['select_role']          = '-- Select role --';
$string['select_cohort']  = '-- Select cohort --';
$string['select_group']   = '-- Select group --';
$string['profile_value_placeholder'] = 'Enter value to match';
$string['csv_upload_desc'] = 'Upload a CSV file with one {$a} per row (header row is optional).';

$string['duplicate_handling'] = 'If a user already has an empty record...';
$string['duplicate_skip']     = 'Skip (do not add another)';
$string['duplicate_add']      = 'Add anyway (allow multiple empty records)';

// ---- Step: preview ----
$string['preview_will_insert'] = '{$a} record(s) will be created.';
$string['preview_will_skip']   = '{$a} record(s) will be skipped (empty record already exists).';
$string['preview_button']      = 'Confirm';

// ---- Step: execute ----
$string['execute_button']  = 'Execute';
$string['result_inserted'] = '{$a} record(s) created successfully.';
$string['result_skipped']  = '{$a} record(s) skipped.';
$string['goto_groupassign'] = 'Assign groups →';
$string['backtocourse']     = 'Back to course';

// ---- Navigation ----
$string['next_button'] = 'Next';
$string['back_button'] = 'Back';

// ---- Delete ----
$string['delete_confirm_heading']      = 'Delete last provisioned batch';
$string['delete_confirm_desc']         = '{$a} record(s) will be permanently deleted.';
$string['delete_confirm_note']         = 'Only records from the last batch that are still empty will be deleted.';
$string['delete_execute_button']       = 'Delete';
$string['delete_success']              = '{$a} record(s) deleted successfully.';
$string['delete_nothing']              = 'No records found to delete.';
$string['delete_no_batch']             = 'No provisioning batch found. Nothing to delete.';
$string['delete_blocked_by_input']     = 'Cannot delete: one or more records in this batch already have data entered.';
$string['delete_blocked_by_input_desc'] = 'To delete individual records, please use the database module\'s entry management directly.';

// ---- Errors ----
$string['error_no_db_selected'] = 'Please select at least one database module.';
$string['error_field_mismatch'] = 'The selected databases have different field configurations. Please select databases with identical fields (same names and types).';

// ---- Settings ----
$string['settings_userfields_heading']      = 'User fields available as data source';
$string['settings_userfields_desc']         = 'Select which mdl_user fields can be used as a data source in field mapping.';
$string['settings_enabled_user_fields']     = 'Enabled user fields';
$string['settings_enabled_user_fields_desc'] = 'These fields will appear in the profile field selector on the mapping screen.';
$string['settings_mapping_heading']         = 'Default field mapping';
$string['settings_mapping_desc']            = 'Define default mappings by database field name (exact match). These are applied when no mapping has been saved for a block instance.';
$string['settings_mapping_row']             = 'Mapping {$a}';
$string['settings_mapping_fieldname']       = 'Field name (exact match)';
$string['settings_mapping_fieldname_desc']  = 'Enter the exact name of the database field.';
$string['settings_mapping_source']          = 'Default source';
$string['settings_mapping_nosource']        = '-- Not set --';
$string['csv_not_found_warning'] = '{$a} ID(s) in the CSV could not be matched to any Moodle user and will be skipped:';

// ---- Admin bulk provision ----
$string['bulk_provision_heading']           = 'Bulk record provisioning (admin)';
$string['bulk_provision_link']              = 'Open bulk provisioning tool →';
$string['admin_step_select_category']       = 'Step 1: Select category';
$string['admin_select_category_desc']       = 'Select a category. Database modules in all courses under that category (including subcategories) will be listed.';
$string['select_category']                  = '-- Select category --';
$string['admin_selected_category']          = 'Target category:';
$string['admin_step_select_db']             = 'Step 2: Select database modules';
$string['admin_select_db_desc']             = 'Select the database modules to provision. All selected databases must have identical field configurations.';
$string['admin_no_databases_in_category']   = 'No database modules found in courses under this category.';
$string['admin_group_no_grouping']          = '(Grouping selection not available in bulk mode)';
$string['backtoadmin']                      = 'Back to settings';

$string['error_no_category_selected'] = 'Please select a category.';

