-- 0.3.0: consent log
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
