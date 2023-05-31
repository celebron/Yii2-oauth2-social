<?php

namespace Celebron\social;

use Celebron\social\args\RequestArgs;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 *
 * @property-read null|string $code
 * @property-read State $state
 */
class SocialController extends Controller
{
    public SocialConfig $config;

    public function getCode() : ?string
    {
        return $this->request->get('code');
    }

    /**
     * @throws BadRequestHttpException
     */
    public function getState() : State
    {
        $state = $this->request->get('state');
        if($state === null) {
            throw new BadRequestHttpException(
                \Yii::t('yii', 'Missing required parameters: {params}', ['params' => 'state'])
            );
        }

        return State::open($state);
    }

    /**
     * @throws \Exception
     */
    public function actionHandler(string $social)
    {
        \Yii::beginProfile("Social profiling", static::class);
        $requestArgs = new RequestArgs(
            $this->config,
            $this->getCode(),
            $this->getState()
        );
        $object = $this->config->get($social);
        try {
            if($object === null) {
                throw  throw new NotFoundHttpException("Social '{$social}' not registered");
            }

            $user = \Yii::$app->user->identity;
            $response = $object->request($requestArgs);

            $methodName = $this->config->prefixMethod . $this->getState()->normalizeMethod();
            $methodRef = new \ReflectionMethod($user, $methodName);
            if($methodRef->invoke($user, $response, $object)) {
                return $object->success($this, $requestArgs);
            }
            return $object->failed($this, $requestArgs);
        } catch (\Exception $ex) {
            \Yii::error($ex->getMessage(), static::class);
            return $object->error($this, $ex, $requestArgs);
        } finally {
            \Yii::endProfile("Social profiling", static::class);
        }
    }
}