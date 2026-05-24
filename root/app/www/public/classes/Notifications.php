<?php

/*
----------------------------------
 ------  Created: 111723   ------
 ------  Austin Best	   ------
----------------------------------
*/

//-- BRING IN THE EXTRAS
loadClassExtras('Notifications');

class Notifications
{
    use NotificationTemplates;
    use NotificationTests;

    use Mattermost;
    use Notifiarr;
    use Telegram;

    protected $headers;
    protected $logpath;
    protected $serverName;
    protected $database;

    public function __construct()
    {
        global $platforms, $settingsTable, $database;

        $this->database = $database ?? new Database();
        $this->logpath  = LOGS_PATH . 'notifications/';

        $settingsTable    = $settingsTable ?? apiRequest('database/settings');
        $this->serverName = is_array($settingsTable) ? $settingsTable['serverName'] : '';
    }

    public function __toString()
    {
        return 'Notifications initialized';
    }

    public function sendTestNotification($linkId, $name)
    {
        $return                    = '';
        $notificationLinkData      = null;
        $tests                     = $this->getTestPayloads();
        $notificationPlatformTable = $this->database->getNotificationPlatforms();
        $notificationLinkTable     = $this->database->getNotificationLinks();

        foreach ($notificationLinkTable as $notificationLink) {
            if ($notificationLink['id'] == $linkId) {
                $notificationLinkData = $notificationLink;
                break;
            }
        }

        $platformId = $notificationLinkData['platform_id'] ?? $notificationLinkData['platform'] ?? null;
        $notificationPlatform = null;

        foreach ($notificationPlatformTable as $platform) {
            if ($platform['id'] == $platformId) {
                $notificationPlatform = $platform;
                break;
            }
        }

        $platformName = $notificationPlatform['platform'] ?? 'Unknown';

        $logfile = $this->logpath . $platformName . '.log';
        logger($logfile, 'test notification request to ' . $platformName);
        logger($logfile, 'test=' . $name);
        logger($logfile, 'tests=' . json_encode($tests));
        logger($logfile, 'test payload=' . json_encode($tests[$name]));

        $result = $this->notify($linkId, $name, $tests[$name], true);

        if (($result['code'] ?? 200) != 200) {
            $return = 'Code ' . ($result['code'] ?? '') . ', ' . ($result['error'] ?? '');
        }

        return ['code' => $result['code'] ?? 200, 'result' => $return];
    }

    public function notify($linkId, $trigger, $payload, $test = false)
    {
        $linkIds                   = [];
        $notificationPlatformTable = $this->database->getNotificationPlatforms();
        $notificationTriggersTable = $this->database->getNotificationTriggers();
        $notificationLinkTable     = $this->database->getNotificationLinks();
        $triggerFields             = $this->getTemplate($trigger);

        //-- MAKE IT MATCH THE TEMPLATE
        foreach ($payload as $payloadField => $payloadVal) {
            if (!array_key_exists($payloadField, $triggerFields) || !$payloadVal) {
                unset($payload[$payloadField]);
            }
        }

        if ($this->serverName) {
            $payload['server']['name'] = $this->serverName;
        }

        if ($linkId) {
            foreach ($notificationLinkTable as $notificationLink) {
                if ($notificationLink['id'] == $linkId) {
                    $linkIds[] = $notificationLink;
                }
            }
        } else {
            foreach ($notificationTriggersTable as $notificationTrigger) {
                if ($notificationTrigger['name'] == $trigger) {
                    foreach ($notificationLinkTable as $notificationLink) {
                        $triggers = makeArray(json_decode($notificationLink['trigger_ids'], true));

                        foreach ($triggers as $triggerId) {
                            if ($triggerId == $notificationTrigger['id']) {
                                $linkIds[] = $notificationLink;
                            }
                        }
                    }
                    break;
                }
            }
        }

        $results = [];

        foreach ($linkIds as $linkId) {
            $platformId         = $linkId['platform_id'] ?? $linkId['platform'] ?? null;
            $platformParameters = json_decode($linkId['platform_parameters'], true) ?: [];
            $platformName       = '';

            foreach ($notificationPlatformTable as $notificationPlatform) {
                if ($notificationPlatform['id'] == $platformId) {
                    $platformName = $notificationPlatform['platform'];
                    break;
                }
            }

            if (!$platformName) {
                continue;
            }

            $logfile = $this->logpath . $platformName . '.log';

            logger($logfile, 'notification request to ' . $platformName);
            logger($logfile, 'notification payload: ' . json_encode($payload));

            switch ($platformId) {
                case NotificationPlatforms::NOTIFIARR:
                    $results[] = $this->notifiarr(
                        $logfile,
                        $platformParameters['apikey'],
                        $payload,
                        $test
                    );
                    break;

                case NotificationPlatforms::TELEGRAM:
                    $results[] = $this->telegram(
                        $logfile,
                        $platformParameters['botToken'],
                        $platformParameters['chatId'],
                        $platformParameters['messageThreadId'] ?? null,
                        $payload,
                        $test
                    );
                    break;

                case NotificationPlatforms::MATTERMOST:
                    $results[] = $this->mattermost(
                        $logfile,
                        $platformParameters['url'],
                        $payload,
                        $test,
                        $platformParameters['username'] ?? null
                    );
                    break;
            }
        }

        foreach ($results as $result) {
            if (($result['code'] ?? 200) != 200) {
                return $result;
            }
        }

        return end($results) ?: ['code' => 200, 'result' => ''];
    }

    public function getNotificationPlatformNameFromId($id, $platforms)
    {
        foreach ($platforms as $platform) {
            if ($id == $platform['id']) {
                return $platform['platform'];
            }
        }

        return '';
    }

    public function getNotificationTriggerNameFromId($id, $triggers)
    {
        foreach ($triggers as $trigger) {
            if ($id == $trigger['id']) {
                return $trigger['label'];
            }
        }

        return '';
    }
}
