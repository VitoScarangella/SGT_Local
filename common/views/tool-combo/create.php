<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\ToolCombo $model
 */

$this->title = 'Create';
$this->params['breadcrumbs'][] = ['label' => 'Tool Combo ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tool-combo-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
