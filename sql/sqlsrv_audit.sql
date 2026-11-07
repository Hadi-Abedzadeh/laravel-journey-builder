-- READ-ONLY SQL Server schema audit; no modifications.
-- varchar/nvarchar sizes are reported in characters, not sys.columns.max_length bytes.
WITH expected (table_name, column_name, expected_type) AS (
    SELECT * FROM (VALUES
        (N'jb_flows',N'id',N'BIGINT'),
        (N'jb_flows',N'code',N'VARCHAR(100)'),
        (N'jb_flows',N'title',N'NVARCHAR(200)'),
        (N'jb_flows',N'status',N'VARCHAR(20)'),
        (N'jb_flows',N'revision',N'INT'),
        (N'jb_flows',N'graph_json',N'NVARCHAR(MAX)'),
        (N'jb_flows',N'enroll_cursor_at',N'DATETIME2(3)'),
        (N'jb_flows',N'enroll_cursor_id',N'BIGINT'),
        (N'jb_flows',N'created_at',N'DATETIME2(3)'),
        (N'jb_flows',N'updated_at',N'DATETIME2(3)'),
        (N'jb_runs',N'id',N'BIGINT'),
        (N'jb_runs',N'flow_id',N'BIGINT'),
        (N'jb_runs',N'user_id',N'VARCHAR(100)'),
        (N'jb_runs',N'node_id',N'VARCHAR(80)'),
        (N'jb_runs',N'state',N'VARCHAR(20)'),
        (N'jb_runs',N'revision',N'INT'),
        (N'jb_runs',N'next_run_at',N'DATETIME2(3)'),
        (N'jb_runs',N'wait_started_at',N'DATETIME2(3)'),
        (N'jb_runs',N'locked_until',N'DATETIME2(3)'),
        (N'jb_runs',N'completed_at',N'DATETIME2(3)'),
        (N'jb_runs',N'last_error',N'NVARCHAR(2000)'),
        (N'jb_runs',N'created_at',N'DATETIME2(3)'),
        (N'jb_runs',N'updated_at',N'DATETIME2(3)'),
        (N'jb_sms_logs',N'id',N'BIGINT'),
        (N'jb_sms_logs',N'flow_id',N'BIGINT'),
        (N'jb_sms_logs',N'run_id',N'BIGINT'),
        (N'jb_sms_logs',N'node_id',N'VARCHAR(80)'),
        (N'jb_sms_logs',N'user_id',N'VARCHAR(100)'),
        (N'jb_sms_logs',N'template',N'VARCHAR(150)'),
        (N'jb_sms_logs',N'status',N'VARCHAR(20)'),
        (N'jb_sms_logs',N'attempts',N'INT'),
        (N'jb_sms_logs',N'http_status',N'SMALLINT'),
        (N'jb_sms_logs',N'error_message',N'NVARCHAR(2000)'),
        (N'jb_sms_logs',N'sent_at',N'DATETIME2(3)'),
        (N'jb_sms_logs',N'created_at',N'DATETIME2(3)'),
        (N'jb_sms_logs',N'updated_at',N'DATETIME2(3)'),
        (N'jb_api_logs',N'id',N'BIGINT'),
        (N'jb_api_logs',N'flow_id',N'BIGINT'),
        (N'jb_api_logs',N'run_id',N'BIGINT'),
        (N'jb_api_logs',N'node_id',N'VARCHAR(80)'),
        (N'jb_api_logs',N'user_id',N'VARCHAR(100)'),
        (N'jb_api_logs',N'method',N'VARCHAR(8)'),
        (N'jb_api_logs',N'target_host',N'VARCHAR(253)'),
        (N'jb_api_logs',N'status',N'VARCHAR(20)'),
        (N'jb_api_logs',N'attempts',N'INT'),
        (N'jb_api_logs',N'http_status',N'SMALLINT'),
        (N'jb_api_logs',N'error_message',N'NVARCHAR(2000)'),
        (N'jb_api_logs',N'sent_at',N'DATETIME2(3)'),
        (N'jb_api_logs',N'created_at',N'DATETIME2(3)'),
        (N'jb_api_logs',N'updated_at',N'DATETIME2(3)'),
        (N'jb_step_logs',N'id',N'BIGINT'),
        (N'jb_step_logs',N'flow_id',N'BIGINT'),
        (N'jb_step_logs',N'run_id',N'BIGINT'),
        (N'jb_step_logs',N'user_id',N'VARCHAR(100)'),
        (N'jb_step_logs',N'from_node_id',N'VARCHAR(80)'),
        (N'jb_step_logs',N'to_node_id',N'VARCHAR(80)'),
        (N'jb_step_logs',N'port',N'VARCHAR(20)'),
        (N'jb_step_logs',N'created_at',N'DATETIME2(3)'),
        (N'jb_api_credentials',N'id',N'BIGINT'),
        (N'jb_api_credentials',N'flow_id',N'BIGINT'),
        (N'jb_api_credentials',N'node_id',N'VARCHAR(80)'),
        (N'jb_api_credentials',N'encrypted_token',N'VARCHAR(MAX)'),
        (N'jb_api_credentials',N'created_at',N'DATETIME2(3)'),
        (N'jb_api_credentials',N'updated_at',N'DATETIME2(3)'),
        (N'jb_retry_audit',N'id',N'BIGINT'),
        (N'jb_retry_audit',N'flow_id',N'BIGINT'),
        (N'jb_retry_audit',N'run_id',N'BIGINT'),
        (N'jb_retry_audit',N'node_id',N'VARCHAR(80)'),
        (N'jb_retry_audit',N'retry_kind',N'VARCHAR(16)'),
        (N'jb_retry_audit',N'previous_status',N'VARCHAR(20)'),
        (N'jb_retry_audit',N'previous_error',N'NVARCHAR(2000)'),
        (N'jb_retry_audit',N'created_at',N'DATETIME2(3)'),
        (N'member_data',N'userId',N'VARCHAR(100)'),
        (N'member_data',N'section',N'VARCHAR(20)'),
        (N'member_data',N'key',N'NVARCHAR(50)'),
        (N'member_data',N'name',N'NVARCHAR(50)'),
        (N'member_data',N'sign',N'VARCHAR(30)'),
        (N'jobs',N'queue',N'VARCHAR(30)')
    ) v(table_name,column_name,expected_type)
), actual AS (
    SELECT t.name AS table_name, c.name AS column_name,
        UPPER(ty.name) + CASE
            WHEN ty.name IN ('nvarchar','varchar') THEN '(' + CASE WHEN c.max_length=-1 THEN 'MAX'
                 WHEN ty.name='nvarchar' THEN CONVERT(varchar(20),c.max_length/2)
                 ELSE CONVERT(varchar(20),c.max_length) END + ')'
            WHEN ty.name='datetime2' THEN '(' + CONVERT(varchar(20),c.scale) + ')'
            ELSE '' END AS actual_type
    FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id
    JOIN sys.types ty ON c.user_type_id=ty.user_type_id
    WHERE SCHEMA_NAME(t.schema_id)='dbo'
)
SELECT e.table_name, e.column_name, e.expected_type,
    ISNULL(a.actual_type,'MISSING') AS actual_type,
    CASE WHEN a.actual_type=e.expected_type THEN 'OK' ELSE 'REVIEW WITH DBA' END AS result
FROM expected e LEFT JOIN actual a ON a.table_name=e.table_name AND a.column_name=e.column_name
ORDER BY CASE WHEN a.actual_type=e.expected_type THEN 1 ELSE 0 END, e.table_name, e.column_name;
