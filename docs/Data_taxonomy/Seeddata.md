-- CannaRewards Seed Data
-- This script populates a fresh database with essential data for local development.
-- Import this file into your WordPress database using a tool like Adminer.

-- Clear existing loyalty data to ensure a clean slate
DELETE FROM wp_usermeta WHERE meta_key LIKE '_canna_%';
TRUNCATE TABLE wp_canna_achievements;
TRUNCATE TABLE wp_canna_user_achievements;
TRUNCATE TABLE wp_canna_user_action_log;
TRUNCATE TABLE wp_canna_reward_codes;
DELETE FROM wp_posts WHERE post_type IN ('canna_rank', 'canna_achievement', 'canna_custom_field', 'canna_trigger');
DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);


-- RANKS
-- Note: Post IDs (first value) may need to be adjusted if you have existing content.
INSERT INTO `wp_posts` (`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES
(1001, 1, NOW(), NOW(), '', 'Bronze', '', 'publish', 'closed', 'closed', '', 'bronze', '', '', NOW(), NOW(), '', 0, 'http://your-site.local/?post_type=canna_rank&#038;p=1001', 0, 'canna_rank', '', 0),
(1002, 1, NOW(), NOW(), '', 'Silver', '', 'publish', 'closed', 'closed', '', 'silver', '', '', NOW(), NOW(), '', 0, 'http://your-site.local/?post_type=canna_rank&#038;p=1002', 0, 'canna_rank', '', 0),
(1003, 1, NOW(), NOW(), '', 'Gold', '', 'publish', 'closed', 'closed', '', 'gold', '', '', NOW(), NOW(), '', 0, 'http://your-site.local/?post_type=canna_rank&#038;p=1003', 0, 'canna_rank', '', 0);

INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES
(1001, 'points_required', '1000'),
(1001, 'point_multiplier', '1.2'),
(1001, 'benefits', 'Access to Bronze-tier rewards'),
(1002, 'points_required', '5000'),
(1002, 'point_multiplier', '1.5'),
(1002, 'benefits', 'Early access to new drops\r\nExclusive merch'),
(1003, 'points_required', '10000'),
(1003, 'point_multiplier', '2.0'),
(1003, 'benefits', '2x points on all scans\r\nPriority support');


-- ACHIEVEMENTS (Example)
INSERT INTO `wp_posts` (`ID`, `post_title`, `post_name`, `post_type`, `post_status`) VALUES (1004, 'First Scan', 'first_scan', 'canna_achievement', 'publish');
INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES
(1004, 'achievement_key', 'first_scan'),
(1004, 'points_reward', '100'),
(1004, 'rarity', 'common'),
(1004, 'trigger_event', 'product_scanned'),
(1004, 'trigger_count', '1'),
(1004, 'conditions', '');


-- TRIGGERS (Example)
INSERT INTO `wp_posts` (`ID`, `post_title`, `post_name`, `post_type`, `post_status`) VALUES (1005, 'Referrer Conversion Bonus', 'referrer-conversion-bonus', 'canna_trigger', 'publish');
INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES
(1005, 'event_key', 'referral_converted'),
(1005, 'action_type', 'grant_points'),
(1005, 'action_value', '500');

-- Note: This seed file does not create sample users or products, as those are better created
-- through the WordPress admin UI for a more realistic testing environment.

-- END OF SEED DATA