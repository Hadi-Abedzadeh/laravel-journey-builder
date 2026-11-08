-- Run after the additive v3.2 SMS migration. Read-only inspection, no changes.
SELECT
    s.name AS schema_name,
    t.name AS table_name,
    c.name AS column_name,
    ty.name AS sql_type,
    CASE WHEN c.max_length = -1 THEN 'MAX'
         WHEN ty.name IN ('nvarchar','nchar') THEN CONVERT(varchar(10),c.max_length/2)
         ELSE CONVERT(varchar(10),c.max_length) END AS defined_length,
    c.precision AS numeric_precision,
    c.scale AS numeric_scale,
    c.is_nullable
FROM sys.columns AS c
JOIN sys.tables AS t ON t.object_id = c.object_id
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
JOIN sys.types AS ty ON ty.user_type_id = c.user_type_id
WHERE s.name='dbo' AND t.name IN ('jb_sms_providers','jb_sms_logs')
ORDER BY t.name, c.column_id;

SELECT t.name AS table_name, i.name AS index_name, i.is_unique, i.has_filter,
       i.filter_definition, i.type_desc
FROM sys.indexes AS i
JOIN sys.tables AS t ON t.object_id=i.object_id
WHERE t.name IN ('jb_sms_providers','jb_sms_logs') AND i.name IS NOT NULL
ORDER BY t.name, i.name;

-- No recipient/secret/raw SMS text in the audit output.
SELECT status, COUNT_BIG(*) AS rows_count
FROM dbo.jb_sms_logs
GROUP BY status;
