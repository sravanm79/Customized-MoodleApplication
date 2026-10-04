#!/bin/bash
# Prints moodledata size (bytes), filedir size, file rows and DB size for Moodle.
# The DB password comes from .env (never written into the repository).
DBPW=$(grep '^MOODLE_DB_PASSWORD=' "$(dirname "$0")/../.env" | cut -d= -f2-)
echo "moodledata_bytes $(docker exec moodle_app du -sb /bitnami/moodledata | cut -f1)"
echo "filedir_bytes $(docker exec moodle_app du -sb /bitnami/moodledata/filedir | cut -f1)"
echo "filedir_files $(docker exec moodle_app find /bitnami/moodledata/filedir -type f | wc -l)"
echo "sessions_bytes $(docker exec moodle_app du -sb /bitnami/moodledata/sessions 2>/dev/null | cut -f1)"
docker exec -e MYSQL_PWD="$DBPW" moodle_db mariadb -N -ubn_moodle bitnami_moodle -e "
select 'db_bytes', sum(data_length+index_length) from information_schema.tables where table_schema='bitnami_moodle';
select 'db_mariadb_datadir_note','see du below';
select concat('tbl_',table_name), data_length+index_length, table_rows from information_schema.tables where table_schema='bitnami_moodle' and table_name in ('mdl_files','mdl_assign_submission','mdl_logstore_standard_log','mdl_user','mdl_sessions','mdl_grade_grades','mdl_assign_grades','mdl_task_adhoc','mdl_event');
select 'files_rows_assign', count(*), coalesce(sum(filesize),0) from mdl_files where component='assignsubmission_file' and filename<>'.';
select 'files_rows_draft', count(*), coalesce(sum(filesize),0) from mdl_files where filearea='draft' and filename<>'.';"
echo "mariadb_dir_bytes $(docker exec moodle_db du -sb /bitnami/mariadb/data | cut -f1)"
