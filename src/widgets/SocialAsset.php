<?php
/*
 * Copyright (c) 2023.  Aleksey Shatalin (celebron) <celebron.ru@yandex.ru>
 */

namespace Celebron\socialSource\widgets;

use yii\bootstrap5\BootstrapAsset;

class SocialAsset extends \yii\web\AssetBundle
{
    public $sourcePath = '@Celebron/socialSource/widgets/public';
    public $css = [
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.0/font/bootstrap-icons.css',
        'social.css'
    ];
    public $depends = [
        BootstrapAsset::class,
    ];

}