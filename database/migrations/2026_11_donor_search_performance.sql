-- Remove a redundant donation index. The unique (user_id, donation_date)
-- index already supports donor donation counts and date-ordered history.
SET @database_name = DATABASE();
SET @has_duplicate_index = (
  SELECT COUNT(*) = 2
  FROM information_schema.statistics
  WHERE table_schema = @database_name
    AND table_name = 'donations'
    AND index_name = 'idx_donation_user_date'
    AND (
      SELECT COUNT(*)
      FROM information_schema.statistics
      WHERE table_schema = @database_name
        AND table_name = 'donations'
        AND index_name = 'idx_donation_user_date'
    ) = 2
    AND (
      (seq_in_index = 1 AND column_name = 'user_id')
      OR (seq_in_index = 2 AND column_name = 'donation_date')
    )
);
SET @has_unique_covering_index = (
  SELECT COUNT(*) = 2
     AND SUM(non_unique = 0) = 2
     AND SUM(seq_in_index = 1 AND column_name = 'user_id') = 1
     AND SUM(seq_in_index = 2 AND column_name = 'donation_date') = 1
  FROM information_schema.statistics
  WHERE table_schema = @database_name
    AND table_name = 'donations'
    AND index_name = 'uq_user_date'
);
SET @sql = IF(
  @has_duplicate_index AND @has_unique_covering_index,
  'ALTER TABLE donations DROP INDEX idx_donation_user_date',
  'SELECT 1'
);
PREPARE donor_search_migration FROM @sql;
EXECUTE donor_search_migration;
DEALLOCATE PREPARE donor_search_migration;

-- Rollback (manual, only if explicitly required):
-- ALTER TABLE donations ADD INDEX idx_donation_user_date (user_id, donation_date);
