<?php
/*
 * Copyright (c) 2023.  Aleksey Shatalin (celebron) <celebron.ru@yandex.ru>
 */

namespace Celebron\socialSource\data;

use Celebron\common\Token;
use Celebron\socialSource\events\EventData;
use Celebron\socialSource\interfaces\UrlsInterface;
use Celebron\socialSource\OAuth2;
use Celebron\socialSource\responses\TokenResponse;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\web\BadRequestHttpException;
use Yiisoft\Http\Header;

class TokenData extends AbstractData
{
    public string $grant_type = 'authorization_code';
    public string $client_secret;
    public array $header = [];
    public array $params = [];

    public function __construct (
        public readonly string $code,
        OAuth2 $social,
        array $config = []
    ) {
        parent::__construct($social, $config);
        $this->uri = ($this->social instanceof UrlsInterface) ? $this->social->getUriToken():'';
        $this->client_secret = $this->social->clientSecret;
    }

    public function setAuthorization(string $value) : void
    {
        $this->header[Header::AUTHORIZATION] = $value;
    }

    public function setAuthorizationBasic(string $value, bool $base64 = true) : void
    {
        $this->setAuthorization('Basic ' . ($base64 ? base64_encode($value):$value));
    }

    public function generateData(array $data): array
    {
        $event = new EventData($data);
        $this->social->trigger(OAuth2::EVENT_DATA_TOKEN, $event);
        $data = $event->newData;

        return ArrayHelper::merge([
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => $this->grant_type,
            'code' => $this->code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
        ], $data);
    }

    public function responseToken(array $data = []):Token
    {
        if(empty($this->uri)) {
            throw new BadRequestHttpException(\Yii::t('social','[{request}]Property $uri empty.',[
                'request' => 'requestToken'
            ]));
        }
        $request = $this->client->post($this->uri, $this->generateData($data), $this->header, $this->params);
        $response = $this->send($request);
        return new Token($response->getData());
    }
}