<?php

/**
 * Class Antispam
 */
class Antispam
{

    use \TwitchBot\Module {
        \TwitchBot\Module::__construct as private moduleConstructor;
    }

    /**
     * Antispam constructor.
     * @param array $infos
     * @param \TwitchBot\IrcConnect $client
     */
    public function __construct(array $infos, $client)
    {
        $this->moduleConstructor($infos, $client);
    }

    public function onConnect()
    {
        if ($this->getInfo('connect_message')) {
           $this->getClient()->sendMessage('Antispam system is working on !');
        }
    }

    /**
     * @param \TwitchBot\Message $data
     */
    public function onMessage($data)
    {
        /**
         * 0 = viewer
         * 1 = sub
         * 2 = mod
         * 3 = broadcaster
         */

        if ($data->getUserType() < 2) {
            $message = strtolower($data->getMessage());
            /** viewer & sub */

            if ($this->getConfig('enable_blacklisterwords')) {
                $isBlacklisted = $this->isBlacklist($message);
                if ($isBlacklisted != false) {
                    $this->timeout($data->getUsername(), $this->getConfig('timeout_blacklistedword'));
                    $message = sprintf($this->getConfig('message_blacklistedword'), $data->getUsername(), $isBlacklisted);
                    $this->getClient()->sendMessage($message);
                }
            }

            if ($this->getConfig('enable_linkdetection')) {
                if ($this->asLink($message) AND !$this->isAuthorizedPepopleLink($data->getUsername())) {
                    $this->timeout($data->getUsername(), $this->getConfig('timeout_link'));
                    $message = sprintf($this->getConfig('message_timeout_link'), $data->getUsername());
                    $this->getClient()->sendMessage($message);
                }
            }

            if ($this->getConfig('enable_toolong')) {
                if ($this->isTooLong($message)) {
                    $this->timeout($data->getUsername(), $this->getConfig('timeout_toolong'));
                    $message = sprintf($this->getConfig('message_timeout_toolong'), $data->getUsername());
                    $this->getClient()->sendMessage($message);
                }
            }

            if ($this->getConfig('enable_toomanycaps')) {
                if ($this->tooManyCaps($data->getMessage())) {
                    $this->timeout($data->getUsername(), $this->getConfig('timeout_toomanycaps'));
                    $message = sprintf($this->getConfig('message_timeout_toomanycaps'), $data->getUsername());
                    $this->getClient()->sendMessage($message);
                }
            }

        }

    }

    /**
     * @param \TwitchBot\Command $command
     * @return bool
     */
    public function onCommand($command)
    {
        if ($command == "permitlink" AND $command->getMessage()->getUserType() == 3) {
            $args = $command->getArgs();
            switch ($args[1]) {
                case 'on':
                    return $this->addPermitPeopleLink($args[2]);
                    break;
                case 'off':
                    return $this->removePermitPeopleLink($args[2]);
                    break;
                default:
                    $this->getClient()->sendMessage('Invalid usage for permitlink command. Usage: permitlink on/off username');
                    break;
            }
        }

        return true;
    }

    /**
     * @param $message
     * @return bool
     */
    private function isBlacklist($message)
    {
        foreach ($this->getConfig('blacklisted_word') as $level => $words) {
            foreach ($words as $word) {
                if (preg_match('/' . $word . '/', $message)) {
                    return $level;
                }
            }
        }

        return false;
    }

    /**
     * @param $message
     * @return bool
     */
    private function asLink($message)
    {
        $regex = "#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si";
        if (preg_match($regex, $message, $matches)) {
            if (preg_match('#http#', $matches[0])) {
                $domain = parse_url($matches[0], PHP_URL_HOST);
            } else {
                $domain = $matches[0];
            }
            return ($this->isAuthorizedDomain($domain)) ? false : true;
        } else {
            return false;
        }
    }

    /**
     * @param $url
     * @return bool
     */
    private function isAuthorizedDomain($url)
    {
        foreach ($this->getConfig('whitelist_domain') as $link) {
            if ($link == $url) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $message
     * @return bool
     */
    private function isTooLong($message)
    {
        if (strlen($message) > $this->getConfig('max_lenght')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $message
     * @return bool
     */
    private function tooManyCaps($message)
    {
        $capsCount = strlen(preg_replace('![^A-Z]+!', '', $message));
        $messageLenght = strlen($message);

        $pourcentCaps = $capsCount * 100 / $messageLenght;

        if ($pourcentCaps > $this->getConfig('pourcent_caps') AND $messageLenght > 8) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * @param $user
     * @return bool
     */
    private function isAuthorizedPepopleLink($user)
    {
        $user = strtolower($user);
        $authorizedPeoples = $this->getConfig('authorized_people');

        foreach ($authorizedPeoples as $username) {
            if ($user == $username) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param $people
     * @return bool
     */
    private function addPermitPeopleLink($people)
    {
        $storage = $this->getConfig('authorized_people');
        $user = strtolower($people);

        if (!in_array($user, $storage)) {
            $storage[] = $user;
            $this->setConfig('authorized_people', $storage);
            $message = sprintf($this->getConfig('message_link_add'), $user);
            $this->getClient()->sendMessage($message);
        } else {
            $message = sprintf($this->getConfig('message_link_already_add'), $user);
            $this->getClient()->sendMessage($message);
        }

        return true;
    }

    /**
     * @param $people
     * @return bool
     */
    private function removePermitPeopleLink($people)
    {
        $storage = $this->getConfig('authorized_people');
        $user = strtolower($people);

        $key = array_search($user, $storage);
        if ($key !== false) {
            unset($storage[$key]);
            $this->setConfig('authorized_people', $storage);

            $message = sprintf($this->getConfig('message_link_remove'), $user);
            $this->getClient()->sendMessage($message);
        } else {
            $message = sprintf($this->getConfig('message_link_already_remove'), $user);
            $this->getClient()->sendMessage($message);
        }

        return true;
    }

    /**
     * @param $user
     * @param $time
     */
    private function timeout($user, $time)
    {
        $this->getClient()->sendMessage('.timeout ' . $user . ' ' . $time);
        $this->getClient()->sendToLog('User ' . $user . ' tiemout ' . $time);
    }

}
