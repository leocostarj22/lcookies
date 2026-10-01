-- 0.3.0: consent log
CREATE TABLE IF NOT EXISTS `#__lcookies_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consent_uuid` char(36) NOT NULL,
  `action` varchar(20) NOT NULL,
  `categories` varchar(2000) NOT NULL DEFAULT '[]',
  `policy_version` int unsigned NOT NULL DEFAULT 1,
  `user_id` int unsigned NULL,
  `ip_hash` char(64) NOT NULL DEFAULT '',
  `ua_hash` char(64) NOT NULL DEFAULT '',
  `url` varchar(2048) NOT NULL DEFAULT '',
  `language` varchar(7) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uuid` (`consent_uuid`),
  KEY `idx_created` (`created`),
  KEY `idx_ip_created` (`ip_hash`, `created`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
