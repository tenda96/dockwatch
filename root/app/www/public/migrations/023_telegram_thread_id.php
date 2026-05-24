<?php

/*
----------------------------------
 ------  Created: 052626   ------
 ------  Luca Tenderini   ------
----------------------------------
*/

$q = [];

$q[] = "UPDATE " . NOTIFICATION_PLATFORM_TABLE . "
    SET parameters = '{\"botToken\":{\"label\":\"Bot token\",\"description\":\"The token for your bot (google: how to create a telegram bot via godfather)\",\"type\":\"text\",\"required\":\"true\"}, \"chatId\":{\"label\":\"Chat id\",\"description\":\"The chat id for the channel where messages go, should start with -100 (google: gist nafiesl get-chat-id-for-a-channel)\",\"type\":\"text\",\"required\":\"true\"}, \"messageThreadId\":{\"label\":\"Message thread id\",\"description\":\"Optional Telegram forum topic/thread ID. Leave empty to send messages to the main chat.\",\"type\":\"text\"}}'
    WHERE id = " . NotificationPlatforms::TELEGRAM;

$q[] = "UPDATE " . SETTINGS_TABLE . "
    SET value = '023'
    WHERE name = 'migration'";

foreach ($q as $query) {
    logger(MIGRATION_LOG, '<span class=\"text-success\">[Q]</span> ' . preg_replace('!\\s+!', ' ', $query));
    $database->query($query);

    if ($database->error() != 'not an error') {
        logger(MIGRATION_LOG, '<span class=\"text-info\">[R]</span> ' . $database->error(), 'error');
    } else {
        logger(MIGRATION_LOG, '<span class=\"text-info\">[R]</span> query applied!');
    }
}
