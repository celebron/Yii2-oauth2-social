<?php


use Celebron\socialSource\behaviors\ViewerBehavior;
use Celebron\socialSource\Configuration;
use Celebron\socialSource\interfaces\ViewerInterface;
use Celebron\socialSource\Social;
use yii\helpers\Html;


/* @var \yii\web\View $this */
/* @var Social|ViewerInterface|ViewerBehavior $social */
/* @var Configuration $configure */

$statusPlay = Html::a("<i class='bi bi-play'></i>", $social->url('register'));
$statusStop = "<i class='bi bi-stop'></i> " . \Yii::t('social', 'Not authorized');
$statusRegistered = Html::a("<i class='bi bi-toggle2-on'></i>", $social->url('delete'));
$statusDeleted = Html::a("<i class='bi bi-toggle2-off'></i>", $social->url('register'));
?>

<?php if($this->context->idView): ?>
    <td class="social-name">
        <?= $social->name ?>
    </td>
    <?php if (empty($social->getSocialId())): ?>
    <td class="social-id empty">
        <?= $statusStop ?>
    </td>
    <td class="social-manage empty">
        <?= $statusDeleted ?>
    </td>
    <?php else: ?>
    <td class="social-id isset">
        <?= $statusPlay ?>
        <?= $social->getSocialId() ?>
    </td>
    <td class="social-manage isset">
        <?= $statusRegistered ?>
    </td>
    <?php endif; ?>
<?php else: ?>
    <?php if (empty($social->getSocialId())): ?>
    <td class="social-name">
        <?= $statusStop ?><?= $social->name ?>
    </td>
    <td class="social-manage empty">
        <?= $statusDeleted ?>
    </td>
    <?php else: ?>
    <td class="social-name">
        <?= $statusPlay ?>
        <?= $social->name ?>
    </td>
    <td class="social-manage isset">
        <?= $statusRegistered ?>
    </td>
    <?php endif; ?>
<?php endif; ?>