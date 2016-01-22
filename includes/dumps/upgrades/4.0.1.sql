ALTER TABLE `{prefix}listings` ADD COLUMN `reported_as_broken_comments` TEXT NOT NULL AFTER `reported_as_broken`;
UPDATE `{prefix}config` SET `value` =
'<p>Greetings,</p>
<p>Listing "{%TITLE%}" marked as broken.</p>
<p>Comments:</p>
<p>{%COMMENTS%}</p>'
WHERE `config_group` = 'email_templates' AND `name` = 'reported_as_broken_body';