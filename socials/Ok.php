<?php
/*
 * Copyright (c) 2023.  Aleksey Shatalin (celebron) <celebron.ru@yandex.ru>
 */

namespace Celebron\socials;

use Celebron\common\Token;
use Celebron\socialSource\interfaces\UrlsInterface;
use Celebron\socialSource\interfaces\ViewerInterface;
use Celebron\socialSource\OAuth2;
use Celebron\socialSource\data\CodeData;
use Celebron\socialSource\data\IdData;
use Celebron\socialSource\data\TokenData;
use Celebron\socialSource\responses\Code;
use Celebron\socialSource\responses\Id;
use yii\base\InvalidConfigException;
use yii\httpclient\Exception;
use yii\httpclient\Response;
use yii\web\BadRequestHttpException;

/**
 * Oauth2 Ok
 *
 * @property null|string $icon
 * @property string $name
 * @property bool $visible
 *
 * @property-read string $uriCode
 * @property-read string $baseUrl
 * @property-read string $uriInfo
 * @property-read bool $supportRegister
 * @property-read bool $supportLogin
 * @property-read string $uriToken
 */
class Ok extends OAuth2 implements UrlsInterface, ViewerInterface
{
    public string $scope = 'VALUABLE_ACCESS';

    public string $clientPublic;

    private string $_icon = '';
    private ?string $_name;
    private bool $_visible = true;

    /**
     * @throws BadRequestHttpException
     */
    public function requestCode (CodeData $request) : Code
    {
        return $request->request(['scope' => $this->scope]);
    }

    public function requestToken (TokenData $request): Token
    {
        return $request->responseToken();
    }

    protected function sig(array $params, $token):string
    {
        $secret = md5($token . $this->clientSecret);
        return md5(http_build_query($params,arg_separator: '') . $secret);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function requestId (IdData $request): Id
    {

        $params = [
            'application_key' => $this->clientPublic,
            'format' => 'json',
            'fields' => 'uid',
        ];

        $token = $request->getAccessToken();
        ksort($params);
        $params['sig'] = $this->sig($params, $token);
        $params['access_token'] = $token;

        $request->post($params);
        return $request->responseId('uid', handler: function (Response $res):Response {
            if(isset($res->data['error_code'], $res->data['error_msg'])) {
                throw new BadRequestHttpException(\Yii::t('social', '[{socialName}]Error {error} E{statusCode}. {description}', [
                    'socialName' => $this->socialName,
                    'statusCode' => $res->data['error_code'],
                    'description' => $res->data['error_msg'],
                    'error' => '',
                ]));
            }
            return $res;
        });
    }

    public function getBaseUrl (): string
    {
        return  'https://api.ok.ru';
    }

    public function getUriCode (): string
    {
        return 'https://connect.ok.ru/oauth/authorize';
    }

    public function getUriToken (): string
    {
        return 'oauth/token.do';
    }

    public function getUriInfo (): string
    {
        return 'api/users/getCurrentUser';
    }

    public function getSupportManagement (): bool
    {
        return true;
    }

    public function getSupportLogin (): bool
    {
        return true;
    }
}