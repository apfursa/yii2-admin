<?php

namespace wm\admin\jobs\historyLead;

use Bitrix24\Bitrix24Entity;
use wm\admin\models\settings\events\Events;
use wm\b24tools\b24Tools;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

class HistoryLeadSynchronizationDeltaJob extends BaseObject implements \yii\queue\JobInterface
{
    public $modelClass;

    public function execute($queue)
    {
        Yii::$app->params['logPath'] = 'log/';
        $lastId = $this->modelClass::find()->orderBy('ID DESC')->limit(1)->one()->ID;
        $component = new \wm\b24tools\b24Tools();
        $b24App = $component->connectFromAdmin();
        $b24Obj = new \Bitrix24\B24Object($b24App);
        $listDataSelector = 'result.items';
        $params = [
            'entityTypeId' => 1,
            'filter' => [
                '>ID' => $lastId
            ],
        ];
        $request = $b24Obj->client->call(
            'crm.stagehistory.list',
            $params
        );
        foreach (ArrayHelper::getValue($request, $listDataSelector) as $oneEntity) {
            $model = Yii::createObject($this->modelClass);
            $model->loadData($oneEntity);
        }
        $countCalls = (int)ceil($request['total'] / $b24Obj->client::MAX_BATCH_CALLS);
        $data = ArrayHelper::getValue($request, $listDataSelector);
        if (count($data) != $request['total']) {
            for ($i = 1; $i < $countCalls; $i++) {
                $b24Obj->client->addBatchCall('crm.stagehistory.list',
                    array_merge($params, ['start' => $b24Obj->client::MAX_BATCH_CALLS * $i]),
                    function ($result) use ($listDataSelector) {
                        foreach (ArrayHelper::getValue($result, $listDataSelector) as $oneEntity) {
                            $model = Yii::createObject($this->modelClass);
                            $model->loadData($oneEntity);
                        }
                    }
                );
            }
            $b24Obj->client->processBatchCalls();
        }
    }
}