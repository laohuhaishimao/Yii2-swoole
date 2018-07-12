<?php
namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * Site controller
 */
class TestController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return ;
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function indexLogout()
    {
        return 'ok';
    }
}
