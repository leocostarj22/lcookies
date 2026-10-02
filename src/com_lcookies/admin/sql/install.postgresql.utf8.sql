--
-- LCookies: categories, services and cookies
--

CREATE TABLE IF NOT EXISTS "#__lcookies_categories" (
  "id" serial NOT NULL,
  "alias" varchar(100) NOT NULL,
  "title" varchar(255) NOT NULL,
  "description" text,
  "required" smallint DEFAULT 0 NOT NULL,
  "core" smallint DEFAULT 0 NOT NULL,
  "gcm_types" varchar(400) DEFAULT '' NOT NULL,
  "state" smallint DEFAULT 1 NOT NULL,
  "ordering" bigint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" bigint DEFAULT 0 NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" bigint DEFAULT 0 NOT NULL,
  "checked_out" integer,
  "checked_out_time" timestamp without time zone,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__lcookies_categories_idx_alias" UNIQUE ("alias")
);
CREATE INDEX IF NOT EXISTS "#__lcookies_categories_idx_state" ON "#__lcookies_categories" ("state");

CREATE TABLE IF NOT EXISTS "#__lcookies_services" (
  "id" serial NOT NULL,
  "category_id" bigint NOT NULL,
  "alias" varchar(100) NOT NULL,
  "title" varchar(255) NOT NULL,
  "provider" varchar(255) DEFAULT '' NOT NULL,
  "privacy_url" varchar(2048) DEFAULT '' NOT NULL,
  "description" text,
  "block_patterns" text,
  "head_code" text,
  "body_code" text,
  "state" smallint DEFAULT 1 NOT NULL,
  "ordering" bigint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" bigint DEFAULT 0 NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" bigint DEFAULT 0 NOT NULL,
  "checked_out" integer,
  "checked_out_time" timestamp without time zone,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__lcookies_services_idx_alias" UNIQUE ("alias")
);
CREATE INDEX IF NOT EXISTS "#__lcookies_services_idx_category" ON "#__lcookies_services" ("category_id");
CREATE INDEX IF NOT EXISTS "#__lcookies_services_idx_state" ON "#__lcookies_services" ("state");

CREATE TABLE IF NOT EXISTS "#__lcookies_cookies" (
  "id" serial NOT NULL,
  "service_id" bigint NOT NULL,
  "name" varchar(255) NOT NULL,
  "display_name" varchar(255) DEFAULT '' NOT NULL,
  "match_type" varchar(10) DEFAULT 'exact' NOT NULL,
  "type" varchar(20) DEFAULT 'cookie' NOT NULL,
  "domain" varchar(255) DEFAULT '' NOT NULL,
  "duration_value" bigint DEFAULT 0 NOT NULL,
  "duration_unit" varchar(10) DEFAULT 'session' NOT NULL,
  "description" text,
  "source" varchar(20) DEFAULT 'manual' NOT NULL,
  "state" smallint DEFAULT 1 NOT NULL,
  "ordering" bigint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" bigint DEFAULT 0 NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" bigint DEFAULT 0 NOT NULL,
  "checked_out" integer,
  "checked_out_time" timestamp without time zone,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__lcookies_cookies_idx_service" ON "#__lcookies_cookies" ("service_id");
CREATE INDEX IF NOT EXISTS "#__lcookies_cookies_idx_state" ON "#__lcookies_cookies" ("state");
CREATE INDEX IF NOT EXISTS "#__lcookies_cookies_idx_name" ON "#__lcookies_cookies" ("name");

--
-- Consent log (proof of consent). IP addresses are truncated and hashed, never stored.
--
CREATE TABLE IF NOT EXISTS "#__lcookies_consents" (
  "id" bigserial NOT NULL,
  "consent_uuid" char(36) NOT NULL,
  "action" varchar(20) NOT NULL,
  "categories" varchar(2000) DEFAULT '[]' NOT NULL,
  "policy_version" integer DEFAULT 1 NOT NULL,
  "user_id" integer,
  "ip_hash" char(64) DEFAULT '' NOT NULL,
  "ua_hash" char(64) DEFAULT '' NOT NULL,
  "url" varchar(2048) DEFAULT '' NOT NULL,
  "language" varchar(7) DEFAULT '' NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__lcookies_consents_idx_uuid" ON "#__lcookies_consents" ("consent_uuid");
CREATE INDEX IF NOT EXISTS "#__lcookies_consents_idx_created" ON "#__lcookies_consents" ("created");
CREATE INDEX IF NOT EXISTS "#__lcookies_consents_idx_ip_created" ON "#__lcookies_consents" ("ip_hash", "created");
CREATE INDEX IF NOT EXISTS "#__lcookies_consents_idx_user" ON "#__lcookies_consents" ("user_id");

CREATE TABLE IF NOT EXISTS "#__lcookies_scans" (
  "id" serial NOT NULL,
  "status" varchar(10) DEFAULT 'running' NOT NULL,
  "source" varchar(10) DEFAULT 'manual' NOT NULL,
  "pages" integer DEFAULT 0 NOT NULL,
  "results" text,
  "unknown" integer DEFAULT 0 NOT NULL,
  "issues" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" integer DEFAULT 0 NOT NULL,
  "finished" timestamp without time zone,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__lcookies_scans_idx_created" ON "#__lcookies_scans" ("created");

--
-- Default data. Titles and descriptions are language constants, translated on display.
--

INSERT INTO "#__lcookies_categories" ("id", "alias", "title", "description", "required", "core", "gcm_types", "state", "ordering", "created", "modified") VALUES
(1, 'necessary', 'COM_LCOOKIES_CAT_NECESSARY', 'COM_LCOOKIES_CAT_NECESSARY_DESC', 1, 1, '["security_storage"]', 1, 1, NOW(), NOW()),
(2, 'preferences', 'COM_LCOOKIES_CAT_PREFERENCES', 'COM_LCOOKIES_CAT_PREFERENCES_DESC', 0, 0, '["functionality_storage","personalization_storage"]', 1, 2, NOW(), NOW()),
(3, 'statistics', 'COM_LCOOKIES_CAT_STATISTICS', 'COM_LCOOKIES_CAT_STATISTICS_DESC', 0, 0, '["analytics_storage"]', 1, 3, NOW(), NOW()),
(4, 'marketing', 'COM_LCOOKIES_CAT_MARKETING', 'COM_LCOOKIES_CAT_MARKETING_DESC', 0, 0, '["ad_storage","ad_user_data","ad_personalization"]', 1, 4, NOW(), NOW()),
(5, 'unclassified', 'COM_LCOOKIES_CAT_UNCLASSIFIED', 'COM_LCOOKIES_CAT_UNCLASSIFIED_DESC', 0, 1, '[]', 1, 5, NOW(), NOW())
ON CONFLICT DO NOTHING;

INSERT INTO "#__lcookies_services" ("id", "category_id", "alias", "title", "provider", "description", "block_patterns", "state", "ordering", "created", "modified") VALUES
(1, 1, 'website', 'COM_LCOOKIES_SVC_WEBSITE', '', 'COM_LCOOKIES_SVC_WEBSITE_DESC', '', 1, 1, NOW(), NOW())
ON CONFLICT DO NOTHING;

INSERT INTO "#__lcookies_cookies" ("id", "service_id", "name", "display_name", "match_type", "type", "duration_value", "duration_unit", "description", "ordering", "created", "modified") VALUES
(1, 1, '^[a-f0-9]{32}$', 'COM_LCOOKIES_COOKIE_SESSION_NAME', 'regex', 'cookie', 0, 'session', 'COM_LCOOKIES_COOKIE_SESSION_DESC', 1, NOW(), NOW()),
(2, 1, 'joomla_user_state', '', 'exact', 'cookie', 0, 'session', 'COM_LCOOKIES_COOKIE_USER_STATE_DESC', 2, NOW(), NOW()),
(3, 1, 'joomla_remember_me_', '', 'prefix', 'cookie', 60, 'day', 'COM_LCOOKIES_COOKIE_REMEMBER_DESC', 3, NOW(), NOW()),
(4, 1, 'lcookies_consent', '', 'exact', 'cookie', 180, 'day', 'COM_LCOOKIES_COOKIE_CONSENT_DESC', 4, NOW(), NOW())
ON CONFLICT DO NOTHING;

SELECT setval('#__lcookies_categories_id_seq', (SELECT COALESCE(MAX("id"), 0) + 1 FROM "#__lcookies_categories"), false);
SELECT setval('#__lcookies_services_id_seq', (SELECT COALESCE(MAX("id"), 0) + 1 FROM "#__lcookies_services"), false);
SELECT setval('#__lcookies_cookies_id_seq', (SELECT COALESCE(MAX("id"), 0) + 1 FROM "#__lcookies_cookies"), false);
