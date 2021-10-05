CREATE TABLE /*_*/pq_score_log (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `revision_id` int unsigned NOT NULL,
  `timestamp` int(50),
  `old_score` int(10),
  `new_score` int(10)
);

CREATE INDEX /*i*/pq_score_log_index ON /*_*/pq_score_log (id);
