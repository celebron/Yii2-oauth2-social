<?php

namespace Celebron\social;

use Celebron\social\events\EventData;
use Celebron\social\interfaces\GetUrlsInterface;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;


/**
 *
 * @property null|array $state
 */
class RequestCode extends BaseObject
{
    public string $response_type = 'code';
    public string $client_id;
    public string $redirect_uri;
    public string $uri;

    public array $data = [];


    /**
     */
    public function __construct (protected AbstractOAuth2 $social, public State $state, array $config = [])
    {
        parent::__construct($config);
        $this->uri = ($this->social instanceof  GetUrlsInterface) ? $social->getUriCode() : '';
        $this->client_id = $this->social->clientId;
        $this->redirect_uri = $this->social->redirectUrl;
    }

    public function generateUri() : array
    {
        $event = new EventData($this->data);
        $this->social->trigger(AbstractOAuth2::EVENT_DATA_CODE, $event);
        $this->data = $event->newData;

        if(empty($this->uri)) {
            throw new BadRequestHttpException('[RequestCode] Property $uri empty.');
        }

        $default = [
            0 => $this->uri,
            'response_type' => $this->response_type,
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'state' => (string)$this->state,
        ];
        return ArrayHelper::merge($default, $this->data);
    }
}