ALTER TABLE /*_*/pq_score_log DROP COLUMN timestamp;
ALTER TABLE /*_*/pq_score_log RENAME COLUMN timestamp2 TO timestamp;
