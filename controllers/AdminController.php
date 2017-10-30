<?php

namespace app\controllers;

use app\controllers\actions\ErrorAction;
use app\models\AppInfo;
use app\models\AppInfoSearch;
use app\models\AppShareConf;
use app\models\ComponentInfo;
use app\models\LoginForm;
use app\models\RequestLogger;
use app\models\RespMsg;
use app\services\RequestLogService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

class AdminController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => [
                    'logout', 'req-log-index', 'req-log-view', 'app-info-index', 'app-info-view',
                    'app-info-delete', 'get-app-base-info', 'app-info-update', 'app-info-clear-quota',
                    'index', 'component-info-view', 'component-clear-quota'
                ],
                'rules' => [
                    [
                        'actions' => [
                            'logout', 'req-log-index', 'req-log-view', 'app-info-index', 'app-info-view',
                            'app-info-delete', 'get-app-base-info', 'app-info-update', 'app-info-clear-quota',
                            'index', 'component-info-view', 'component-clear-quota'
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->renderPartial('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Lists all RequestLogger models.
     * @param string $fromDate 请求开始时间
     * @param string $toDate 请求结束时间
     * @param string $timeConsume 请求耗时
     * @param string $appId 授权方AppId
     * @return mixed
     */
    public function actionReqLogIndex($fromDate = null, $toDate = null, $timeConsume = null, $appId = null)
    {
        $whereParam = null;

        if ($fromDate && $toDate) {
            $whereParam[] = 'between';
            $whereParam[] = 'DATE(reqTime)';
            $whereParam[] = $fromDate;
            $whereParam[] = $toDate;
        } else if ($fromDate) {
            $whereParam['DATE(reqTime)'] = $fromDate;
        }


        $query = RequestLogger::find()->orderBy('id DESC');
        if ($whereParam) {
            $query->where($whereParam);
        }

        if ($timeConsume) {
            $timeConsume = intval($timeConsume);
            $query->andWhere(['>=', 'timeConsume', $timeConsume]);
        }

        if ($appId) {
            $query->andWhere(['appId' => $appId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => ['']]
        ]);

        return $this->render('req-log-index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single RequestLogger model.
     * @param integer $id
     * @return mixed
     */
    public function actionReqLogView($id)
    {
        $model = RequestLogger::findOne($id);
        if (!$model) {
            return $this->renderContent('没有找到关于' . $id . '的信息。');
        }
        return $this->render('req-log-view', [
            'model' => $model,
        ]);
    }

    /**
     * Lists all AppInfo models.
     * @return mixed
     */
    public function actionAppInfoIndex()
    {
        $searchModel = new AppInfo();
        $whereParam = [];
        $params = Yii::$app->request->get('AppInfo');
        $appId = isset($params['appId']) ? $params['appId'] : null;
        if ($appId) {
            $whereParam['appId'] = trim($appId);
        }

        $query = AppInfo::find()->where($whereParam);

        $nickName = isset($params['nickName']) ? $params['nickName'] : null;
        if ($nickName) {
            $query->andWhere(['like', 'nickName', trim($nickName)]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => ['']]
        ]);

        return $this->render('app-info-index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single AppInfo model.
     * @param string $id
     * @return mixed
     */
    public function actionAppInfoView($id)
    {
        $model = AppInfo::findOne($id);
        if (!$model) {
            return $this->renderContent('没有找到关于' . $id . '的信息。');
        }
        return $this->render('app-info-view', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing AppInfo model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionAppInfoDelete($id)
    {
        $model = AppInfo::findOne($id);
        if (!$model) {
            return $this->renderContent('没有找到关于' . $id . '的信息。');
        }
        $model->delete();
        $key = 'app_access_token_' . $id;
        Yii::$app->cache->delete($key);
        return $this->redirect(['app-info-index']);
    }

    /**
     * 供页面异步请求获取报表数据
     * @param int $type 日志类型（0内部调用，1微信回调）
     * @param int $cycle 0今天，1昨天，2最近7天
     * @return string
     */
    public function actionReport($type, $cycle)
    {
        $respMsg = new RespMsg();
        if (($type == 0 || $type == 1) && ($cycle == 0 || $cycle == 1 || $cycle == 2)) {
            $data = null;
            switch ($cycle) {
                case 0:
                    $data = $this->report($type, date('Y-m-d'), date('Y-m-d'), 'HOUR');
                    break;
                case 1:
                    $quantum = date('Y-m-d', strtotime("-1 day"));
                    $data = $this->report($type, $quantum, $quantum, 'HOUR');
                    break;
                case 2:
                    $quantum = date('Y-m-d', strtotime("-7 day"));
                    $data = $this->report($type, $quantum, date('Y-m-d'), 'DATE');
                    break;
            }
            $respMsg->return_msg = $data;
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '传入参数不合法';
        }
        return $respMsg->toJsonStr();
    }

    /**
     * 查询报表，默认以指定时间为周期查询分组结果。
     * @param int $type
     * @param string $startDate '2016-11-09'
     * @param string $endDate '2016-11-09'
     * @param string $period
     * @return array
     */
    private function report($type, $startDate, $endDate, $period)
    {
        $rows = $period == 'HOUR' ?
            RequestLogService::getRequstLogHourData(['reqDay' => $startDate, 'type' => $type])
            : (new Query())
                ->select(['reqDay as reqTime', 'MIN(minTimeConsume) AS minTimeConsume',
                    'MAX(maxTimeConsume) AS maxTimeConsume', 'sum(number) AS number'])
                ->from('request_log_hour')
                ->where(['type' => $type])
                ->andWhere(['BETWEEN', 'reqDay', $startDate, $endDate])
                ->groupBy('reqDay')
                ->all();

        $xAxisData = $yAxisDataNumber = $yAxisDataMinTimeConsume = $yAxisDataMaxTimeConsume = null;
        foreach ($rows as $row) {
            $xAxisData[] = $row['reqTime'];
            $yAxisDataNumber[] = $row['number'];
            $yAxisDataMinTimeConsume[] = $row['minTimeConsume'];
            $yAxisDataMaxTimeConsume[] = $row['maxTimeConsume'];
        }
        return [
            'xAxisData' => $xAxisData,
            'yAxisDataNumber' => $yAxisDataNumber,
            'yAxisDataMinTimeConsume' => $yAxisDataMinTimeConsume,
            'yAxisDataMaxTimeConsume' => $yAxisDataMaxTimeConsume
        ];
    }

    /**
     * 通过接口去微信重新获取此公众号基本信息
     * @param string $id 公众号AppId
     * @return string
     */
    public function actionGetAppBaseInfo($id)
    {
        $respMsg = new RespMsg();

        $appInfo = AppInfo::findOne($id);
        if (!$appInfo) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $id . '的信息。';
        } else {
            $respMsg = Yii::$app->weiXinService->getComponentAccessToken();
            if ($respMsg->return_code === RespMsg::SUCCESS) {
                $respMsg = $appInfo->getAuthorizeInfo($respMsg->return_msg['accessToken']);
            }
        }
        return $respMsg->toJsonStr();
    }

    /**
     * 进入更新页面
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionAppInfoUpdate($id)
    {
        $model = AppInfo::findOne($id);
        if (!$model) {
            return $this->renderContent('没有找到关于 ' . $id . ' 的信息。');
        }
        return $this->render('app-info-update', ['model' => $model]);
    }

    /**
     * 更新数据
     * If update is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionAppInfoUpdated()
    {
        if (Yii::$app->request->isPost) {
            $appInfo = Yii::$app->request->post('AppInfo');
            $model = AppInfo::findOne($appInfo['appId']);
            $model->accessToken = $appInfo['accessToken'];
            $model->infoType = $appInfo['infoType'];
            $model->update();
            return $this->redirect(['app-info-view?id=' . $model->appId]);
        }
        return $this->renderContent('非法请求类型');
    }

    /**
     * 对公众号的所有API调用（包括第三方代公众号调用）次数进行清零
     * @param $id 公众号AppId
     * @return string
     */
    public function actionAppInfoClearQuota($id)
    {
        $respMsg = new RespMsg();
        $model = AppInfo::findOne($id);
        if (!$model) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $id . '的信息。';
        }

        $respMsg = Yii::$app->weiXinService->clearAppQuota($model);
        return $respMsg->toJsonStr();
    }

    /**
     * 第三方平台信息
     */
    public function actionComponentInfoView()
    {
        $cmtInfos = ComponentInfo::find()->all();

        return $this->render('component-info-view', [
            'cmtInfos' => $cmtInfos,
        ]);
    }

    /**
     * 清空公众号缓存数据<br>
     * 如果不指定具体是哪个公众号，则默认是全部公众号
     * @param null $id 公众号AppId
     * @return \yii\web\Response
     */
    public function actionAppDeleteCache($id = null)
    {
        $query = AppInfo::find()->select(['appId']);
        if ($id) {
            $query->where(['appId' => $id]);
        }
        $appInfos = $query->all();
        foreach ($appInfos as $appInfo) {
            $key = 'app_access_token_' . $appInfo->appId;
            Yii::$app->cache->delete($key);
        }
        if ($id) {
            return $this->redirect(['admin/app-info-view', 'id' => $id]);
        }
        return $this->redirect(['admin/app-info-index']);
    }

    /**
     * 清空第三方公众平台缓存数据<br>
     * 如果不指定具体是哪个公众号，则默认是全部公众号
     * @param string $id 公众号AppId
     * @return RespMsg
     */
    public function actionComponentDeleteCache($id)
    {
        $respMsg = new RespMsg();
        $condition = ['appId' => $id];
        $Infos = ComponentInfo::find()->select(['appId'])->where($condition)->all();
        foreach ($Infos as $info) {
            $key = 'component_access_token_' . $info->appId;
            Yii::$app->cache->delete($key);
        }

        return $respMsg;
    }

    /**
     * 第三方平台对其所有API调用次数清零（只与第三方平台相关，与公众号无关，接口如api_component_token）
     * @param string $id 第三方公众号公众号AppId
     * @return string
     */
    public function actionComponentClearQuota($id)
    {
        $respMsg = new RespMsg();
        $model = ComponentInfo::findOne($id);
        if (!$model) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $id . '的信息。';
        }

        $respMsg = Yii::$app->weiXinService->clearComponentQuota($model);

        return $respMsg;
    }

    /**
     * 查看默认分享公众号列表
     */
    public function actionAppShareView()
    {
        $list = AppShareConf::find()->all();

        return $this->render('app-share-view', ['list' => $list]);
    }

    /**
     * 分享公众号接口调用次数清零<br>
     *
     * 不通过第三方平台的方式，直接调用自己公众号的接口来实现清零的操作。
     */
    public function actionAppShareClearQuota()
    {
        //TODO 需要补充
        $respMsg = new RespMsg();

        return $respMsg;
    }

}
