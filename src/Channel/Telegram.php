<?php

namespace App\Channel;


class Telegram implements ChannelInterface
{
    const FILE_URL = '/file/bot';

    /**
     * @var \Telegram\Bot\Api
     */
    private $client;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $hostname;

    /**
     * @var array
     */
    private $availableLanguages = [];

    /**
     * Telegram constructor.
     * @param array $config
     * @throws \App\Exceptions\Config
     */
    public function __construct($config)
    {
        if (!isset($config['hostname']) || !isset($config['token']) || !isset($config['available_languages'])) {
            throw new \App\Exceptions\Config('Invalid config.');
        }
        $this->setClient($this->prepareClient($config));
        $this->setAvailableLanguages($config['available_languages']);
        $this->setToken($config['token']);
        $this->setHostname($config['hostname']);
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * @param string $hostname
     *
     * @return $this
     */
    public function setHostname($hostname)
    {
        $this->hostname = $hostname;

        return $this;
    }

    /**
     * @param int $chatId
     * @param string $body
     *
     * @return bool
     */
    public function sendMessage($chatId, $body)
    {
        try {
            $this->getClient()->sendMessage([
                'chat_id' => $chatId,
                'text' => $body,
                'reply_markup' => $this->getReplyMarkup(),
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    protected function getReplyMarkup()
    {
        return $this->getClient()->replyKeyboardMarkup([
            'keyboard' => [$this->getAvailableLanguages()],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    /**
     * @return array
     */
    public function getAvailableLanguages()
    {
        return $this->availableLanguages;
    }

    /**
     * @param array $availableLanguages
     *
     * @return $this
     */
    public function setAvailableLanguages($availableLanguages)
    {
        $this->availableLanguages = $availableLanguages;

        return $this;
    }

    /**
     * @param int $id
     *
     * @return string
     */
    public function getChatFileLink($id)
    {
        return $this->getFilelUrl() . $this->getClient()->getFile(['file_id' => $id])->getFilePath();
    }

    /**
     * @return string
     */
    protected function getFilelUrl()
    {
        return $this->getHostname() . self::FILE_URL . $this->getToken() . '/';
    }

    /**
     * @return \Telegram\Bot\Api
     */
    public function getClient()
    {
        return $this->client;
    }

    public function prepareClient($config)
    {
        return new \Telegram\Bot\Api($config['token']);
    }

    /**
     * @param \Telegram\Bot\Api $client
     *
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;

        return $this;
    }
}