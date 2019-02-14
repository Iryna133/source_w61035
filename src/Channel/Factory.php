<?php

namespace App\Channel;

class Factory
{
    public function getInstance($config)
    {
        if (!isset($config['type'])) {
            throw new \Exception('Invalid channel settings');
        }

        if ($config['type'] === 'telegram') {
            $instance = new \App\Channel\Telegram($config);
        }

        return $instance;
    }
}