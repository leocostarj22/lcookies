-- 0.5.0: cookie scanner
CREATE TABLE IF NOT EXISTS `#__lcookies_scans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(10) NOT NULL DEFAULT 'running',
  `source` varchar(10) NOT NULL DEFAULT 'manual',
  `pages` int unsigned NOT NULL DEFAULT 0,
  `results` mediumtext,
  `unknown` int unsigned NOT NULL DEFAULT 0,
  `issues` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `finished` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
