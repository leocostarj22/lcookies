--
-- LCookies: categories, services and cookies
--

CREATE TABLE IF NOT EXISTS `#__lcookies_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `required` tinyint NOT NULL DEFAULT 0,
  `core` tinyint NOT NULL DEFAULT 0,
  `gcm_types` varchar(400) NOT NULL DEFAULT '',
  `state` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__lcookies_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `alias` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL DEFAULT '',
  `privacy_url` varchar(2048) NOT NULL DEFAULT '',
  `description` text,
  `block_patterns` text,
  `head_code` mediumtext,
  `body_code` mediumtext,
  `state` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`),
  KEY `idx_category` (`category_id`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__lcookies_cookies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL DEFAULT '',
  `match_type` varchar(10) NOT NULL DEFAULT 'exact',
  `type` varchar(20) NOT NULL DEFAULT 'cookie',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `duration_value` int unsigned NOT NULL DEFAULT 0,
  `duration_unit` varchar(10) NOT NULL DEFAULT 'session',
  `description` text,
  `source` varchar(20) NOT NULL DEFAULT 'manual',
  `state` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_state` (`state`),
  KEY `idx_name` (`name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Consent log (proof of consent). IP addresses are truncated and hashed, never stored.
--
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

--
-- Default data. Titles and descriptions are language constants, translated on display.
--

INSERT IGNORE INTO `#__lcookies_categories` (`id`, `alias`, `title`, `description`, `required`, `core`, `gcm_types`, `state`, `ordering`, `created`, `modified`) VALUES
(1, 'necessary', 'COM_LCOOKIES_CAT_NECESSARY', 'COM_LCOOKIES_CAT_NECESSARY_DESC', 1, 1, '["security_storage"]', 1, 1, NOW(), NOW()),
(2, 'preferences', 'COM_LCOOKIES_CAT_PREFERENCES', 'COM_LCOOKIES_CAT_PREFERENCES_DESC', 0, 0, '["functionality_storage","personalization_storage"]', 1, 2, NOW(), NOW()),
(3, 'statistics', 'COM_LCOOKIES_CAT_STATISTICS', 'COM_LCOOKIES_CAT_STATISTICS_DESC', 0, 0, '["analytics_storage"]', 1, 3, NOW(), NOW()),
(4, 'marketing', 'COM_LCOOKIES_CAT_MARKETING', 'COM_LCOOKIES_CAT_MARKETING_DESC', 0, 0, '["ad_storage","ad_user_data","ad_personalization"]', 1, 4, NOW(), NOW()),
(5, 'unclassified', 'COM_LCOOKIES_CAT_UNCLASSIFIED', 'COM_LCOOKIES_CAT_UNCLASSIFIED_DESC', 0, 1, '[]', 1, 5, NOW(), NOW());

INSERT IGNORE INTO `#__lcookies_services` (`id`, `category_id`, `alias`, `title`, `provider`, `description`, `block_patterns`, `state`, `ordering`, `created`, `modified`) VALUES
(1, 1, 'website', 'COM_LCOOKIES_SVC_WEBSITE', '', 'COM_LCOOKIES_SVC_WEBSITE_DESC', '', 1, 1, NOW(), NOW());

INSERT IGNORE INTO `#__lcookies_cookies` (`id`, `service_id`, `name`, `display_name`, `match_type`, `type`, `duration_value`, `duration_unit`, `description`, `ordering`, `created`, `modified`) VALUES
(1, 1, '^[a-f0-9]{32}$', 'COM_LCOOKIES_COOKIE_SESSION_NAME', 'regex', 'cookie', 0, 'session', 'COM_LCOOKIES_COOKIE_SESSION_DESC', 1, NOW(), NOW()),
(2, 1, 'joomla_user_state', '', 'exact', 'cookie', 0, 'session', 'COM_LCOOKIES_COOKIE_USER_STATE_DESC', 2, NOW(), NOW()),
(3, 1, 'joomla_remember_me_', '', 'prefix', 'cookie', 60, 'day', 'COM_LCOOKIES_COOKIE_REMEMBER_DESC', 3, NOW(), NOW()),
(4, 1, 'lcookies_consent', '', 'exact', 'cookie', 180, 'day', 'COM_LCOOKIES_COOKIE_CONSENT_DESC', 4, NOW(), NOW());
