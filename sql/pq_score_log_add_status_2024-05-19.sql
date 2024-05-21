ALTER TABLE /*_*/pq_score_log
	ADD COLUMN new_status tinyint(1),
	ADD COLUMN old_status tinyint(1);
