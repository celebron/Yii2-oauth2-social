<?php

namespace Celebron\social\socials;

use Celebron\social\interfaces\GetUrlsInterface;
use Celebron\social\interfaces\GetUrlsTrait;
use Celebron\social\interfaces\ToWidgetInterface;
use Celebron\social\interfaces\ToWidgetTrait;
use Celebron\social\RequestCode;
use Celebron\social\RequestToken;
use Celebron\social\Social;
use Celebron\social\WidgetSupport;
use yii\base\InvalidConfigException;
use yii\httpclient\Exception;
use yii\web\BadRequestHttpException;

/**
 * Oauth2 VK
 */
#[WidgetSupport]
class VK extends Social implements GetUrlsInterface, ToWidgetInterface
{
    use ToWidgetTrait, GetUrlsTrait;
    public string $clientUrl = 'https://oauth.vk.com';
    public string $uriCode = 'authorize';
    public string $uriToken = 'access_token';
    public string $display = 'page';

    public string $icon = '';
    public ?string $name;
    public bool $visible = true;

    public function requestCode (RequestCode $request) : void
    {
        $request->data = [ 'display' => $this->display ];
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function requestToken (RequestToken $request): void
    {
        $this->id = $this->sendToField($request, 'user_id');
    }
}