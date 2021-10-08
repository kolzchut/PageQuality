CREATE TABLE /*_*/pq_issues (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `pq_type` varchar(50),
  `score` int(10),
  `example` varchar(100)
);

CREATE INDEX /*i*/pq_issues_index ON /*_*/pq_issues (id);
