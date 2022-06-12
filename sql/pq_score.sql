CREATE TABLE /*_*/pq_score (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `score` int(10)
);

CREATE INDEX /*i*/pq_score_index ON /*_*/pq_score (id);
