<?php

namespace App;

class App
{
    /**
     * @var \Telegram\Bot\Api
     */
    public $telegram;


    /**
     * @var \App\Channel\ChannelInterface
     */
    private $channel;

    /**
     * @var \CloudConvert\Api
     */
    public $cloudConvert;

    /**
     * @var string
     */
    public $destinationLanguage = 'English';

    /**
     * @var array
     */
    const DESTINATION_LANGUAGES = [
        'English',
        'Deutsch',
        'Russian',
    ];

    /**
     * @var array
     */
    const DESTINATION_LANGUAGES_MAP = [
        'English' => 'en',
        'Deutsch' => 'de',
        'Russian' => 'ru',
    ];


    public function __construct($config)
    {
        if (!isset($config['channel'])) {
            throw new Exception('Invalid channel settings.');
        }
        $this->setChannel($this->prepareChannel($config['channel']));
        // TODO: refactor to generic converter with own factory
        $this->cloudConvert = new \CloudConvert\Api(CLOUD_CONVERT_API_TOKEN);
    }

    /**
     * @param \App\Channel\ChannelInterface $channel
     *
     * @return $this
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @return \App\Channel\ChannelInterface
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param array $config
     *
     * @return \App\Channel\Factory
     */
    public function prepareChannel($config)
    {
        $factory = new \App\Channel\Factory();

        return $factory->getInstance($config);
    }

    /**
     * @param string $encoded
     * @return string
     */
    public function googleSpeechCloudRequest($encoded)
    {
        // TODO: refactor to use generic translator, with own adapters
        $google = new \Google_Client();
        $google->setApplicationName('MultitranslateBot');
        $google->setDeveloperKey(GOOGLE_TRANSLATOR_API_TOKEN);
        $service = new \Google_Service_CloudSpeechAPI($google);
        $audio = new \Google_Service_CloudSpeechAPI_RecognitionAudio();
        $audio->setContent($encoded);
        $config = new \Google_Service_CloudSpeechAPI_RecognitionConfig();
        $config->setEncoding('FLAC');
        $config->setSampleRate(44100);
        $config->setLanguageCode('pl_PL');
        $request = new \Google_Service_CloudSpeechAPI_SyncRecognizeRequest();
        $request->setAudio($audio);
        $request->setConfig($config);
        $results = $service->speech->syncrecognize($request);

        return $results->getResults()[0]->getAlternatives()[0]->getTranscript();
    }


    public function init()
    {
        // TODO: implement request class for params validation logic
        $content = file_get_contents("php://input");
        $update = json_decode($content, true);

        if (!$update) {
            exit;
        }

        $this->log($content);
        if (isset($update["message"])) {
            $response = $this->prepareResponse($update["message"]);
            $this->response($response);
        }
    }

    /**
     * @param array $response
     *
     * @return array|void
     */
    public function prepareResponse($response)
    {
        $data = [
            'chatId' => $response['chat']['id'],
        ];

        if (isset($response['text']) && in_array($response['text'], self::DESTINATION_LANGUAGES)) {
            return $this->changeDestinationLanguage($response['chat']['id'], $response['text']);
        }

        if (isset($response['voice']) && $response['voice']) {
            $response['text'] = $this->recognizeSpeech($response['voice']['file_id']);
        }

        if (isset($response['text']) && $response['text']) {
            $data['message'] = $this->getMessage($response['text'], $response['chat']['id']);
        }

        return $data;
    }

    /**
     * @param string $chatId
     * @param string $text
     */
    public function changeDestinationLanguage($chatId, $text)
    {
        // TODO: use ORM and create language model
        $connection = mysqli_connect(DB_HOSTNAME, DB_USER, DB_PASSWORD, DB_NAME);
        if (!$connection) {
            return;
        }

        if ($this->checkDestinationLanguage($chatId)) {
            $sql = 'UPDATE destination_language SET language_code = "' . self::DESTINATION_LANGUAGES_MAP[$text] . '" WHERE chat_id = ' . (int) $chatId . ';';
        } else {
            $sql = "INSERT INTO destination_language (`chat_id`, `language_code`) VALUES('" . (int) $chatId . "', '" . self::DESTINATION_LANGUAGES_MAP[$text] . "');";
        }

        mysqli_query($connection, $sql);
        mysqli_close($connection);
    }

    /**
     * @param string $fileId
     * @return array
     */
    public function recognizeSpeech($fileId)
    {
        $file = $this->convert($fileId);

        return $this->recognize($file);
    }

    /**
     * @param string $file
     *
     * @return array
     */
    public function recognize($file)
    {
        $encoded =  base64_encode(file_get_contents($file));

        return $this->googleSpeechCloudRequest($encoded);;
    }

    /**
     * @param string $fileId
     *
     * @return string
     */
    public function convert($fileId)
    {
        $local = './tests/output.flac';
        unlink($local);
        $this->cloudConvert->convert([
            'inputformat' => 'ogg',
            'outputformat' => 'flac',
            'input' => 'download',
            'file' => $this->getChannel()->getChatFileLink($fileId),
            'converteroptions.audio_channels' => 1,
            'converteroptions.frequency' => 48000,
            'converteroptions.audio_codec' => 'FLAC',
        ])
            ->wait()
            ->download($local);

        return $local;
    }

    /**
     * @param string $chatId
     *
     * @return void|string
     */
    public function checkDestinationLanguage($chatId)
    {
        $connection = mysqli_connect(DB_HOSTNAME, DB_USER, DB_PASSWORD, DB_NAME);
        if (!$connection) {
            return;
        }

        $sql = 'SELECT language_code FROM destination_language WHERE chat_id = ' . (int) $chatId . ';';
        $result = mysqli_query($connection, $sql);
        $row = mysqli_fetch_assoc($result);
        $this->log(json_encode($row));
        if ($row) {
            return $row['language_code'];
        }
    }

    /**
     * @param string $chatId
     *
     * @return void|string
     */
    public function getDesinationLanguage($chatId)
    {
        $connection = mysqli_connect(DB_HOSTNAME, DB_USER, DB_PASSWORD, DB_NAME);
        if (!$connection) {
            return;
        }

        $lang = $this->checkDestinationLanguage($chatId);
        if ($lang) {
            return $lang;
        }

        $sql = "INSERT INTO destination_language (`chat_id`, `language_code`) VALUES('" . (int) $chatId . "', '" . self::DESTINATION_LANGUAGES_MAP[$this->destinationLanguage] . "');";
        mysqli_query($connection, $sql);
        mysqli_close($connection);

        return self::DESTINATION_LANGUAGES_MAP[$this->destinationLanguage];
    }

    /**
     * @param string $message
     * @param string $chatId
     *
     * @return string
     */
    public function getMessage($message, $chatId)
    {
        $params = [
            'source' => 'pl',
            'target' => $this->getDesinationLanguage($chatId),
            'q' => $message,
        ];
        $response = json_decode(file_get_contents(GOOGLE_TRANSLATOR_API_URL . '&' . http_build_query($params)), true);

        return $response['data']['translations'][0]['translatedText'];
    }

    /**
     * @param array $response
     *
     * @return void
     */
    public function response($response)
    {
        $this->getChannel()->sendMessage($response['chatId'], $response['message']);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function log($message)
    {
        // TODO: refactor to Logger class
        file_put_contents('logs.txt', $message, FILE_APPEND);
    }
}