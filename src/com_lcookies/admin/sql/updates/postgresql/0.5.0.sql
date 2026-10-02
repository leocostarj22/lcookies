-- 0.5.0: cookie scanner
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
