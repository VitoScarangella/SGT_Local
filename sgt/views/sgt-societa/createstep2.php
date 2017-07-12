<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\SgtSocieta $model
 */

$this->title = 'Create';

$this->params['breadcrumbs'][] = ['label' => 'Societa ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sgt-societa-create">

    <?= $this->render('_formstep2', [
        'model' => $model,
    ]) ?>

</div>