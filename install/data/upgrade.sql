--
-- Seo Panel 4.3.0 changes
--

update `settings` set set_val='4.3.0' WHERE `set_name` LIKE 'SP_VERSION_NUMBER';


UPDATE `searchengines` SET `regex` = '<div.*?class="?g.*?>.*?<div.*?class="r"*?>.*?<a href="(.*?)".*?>.*?<h3.*?>(.*?)<\\/h3>' WHERE `url` LIKE '%google%';

CREATE TABLE `sync_searchengines` (
  `id` int(11) NOT NULL,
  `sync_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `result` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `sync_searchengines` ADD PRIMARY KEY (`id`);
ALTER TABLE `sync_searchengines` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


INSERT INTO `texts` (`category`, `label`, `content`) VALUES 
('seotools', 'note_report_generation', 'We recommend to use cron jobs for reports generation.
This action may cause captcha in search engine results.'),
('panel', 'Sync Search Engines', 'Sync Search Engines');
