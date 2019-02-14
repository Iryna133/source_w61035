<?php

namespace App\Channel;

interface ChannelInterface {

    /**
     * @param int $chatId
     * @param array $body
     *
     * @return bool
     */
    public function sendMessage($chatId, $body);

    /**
     * @param array $availableLanguages
     *
     * @return void
     */
    public function setAvailableLanguages($availableLanguages);

    /**
     * @param int $id
     *
     * @return string
     */
    public function getChatFileLink($id);
}