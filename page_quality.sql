CREATE TABLE /*_*/pq_score_log (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `revision_id` int unsigned NOT NULL,
  `timestamp` int(50),
  `old_score` int(10),
  `new_score` int(10)
);

CREATE INDEX /*i*/pq_score_log_index ON /*_*/pq_score_log (id);

CREATE TABLE /*_*/pq_settings (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `setting` varchar(50),
  `value` varchar(50)
);

CREATE INDEX /*i*/pq_settings_index ON /*_*/pq_settings (id);


CREATE TABLE /*_*/pq_issues (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `pq_type` varchar(50),
  `score` int(10),
  `example` varchar(100)
);

CREATE INDEX /*i*/pq_issues_index ON /*_*/pq_issues (id);


