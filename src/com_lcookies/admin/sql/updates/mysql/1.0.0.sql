-- 1.0.0: name shown to visitors (instead of a pattern or a prefix)
ALTER TABLE `#__lcookies_cookies` ADD COLUMN `display_name` varchar(255) NOT NULL DEFAULT '' AFTER `name`;
UPDATE `#__lcookies_cookies` SET `display_name` = 'COM_LCOOKIES_COOKIE_SESSION_NAME' WHERE `name` = '^[a-f0-9]{32}$' AND `match_type` = 'regex' AND `display_name` = '';
