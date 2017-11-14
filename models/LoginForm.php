<?php

namespace app\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;

/**
 * 登录表单模型
 *
 * @property User|null $user This property is read-only.
 *
 */
class LoginForm extends Model
{
    public $username;
    public $password;
    public $verificationCode;
    public $rememberMe = true;

    private $_user = false;


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password', 'verificationCode'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
            ['verificationCode', 'string', 'min' => 6, 'max' => 7],
            ['verificationCode', 'verificationCode'],
        ];
    }

    /**
     * 验证用户输入的验证码
     *
     * @param $attribute
     * @param $params
     * @throws InvalidConfigException
     */
    public function verificationCode($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $captcha = Yii::$app->createController('common/captcha');
            if ($captcha === false) {
                throw new InvalidConfigException('Invalid CAPTCHA action ID: captcha');
            }

            /* @var $controller \yii\base\Controller */
            list($controller, $actionID) = $captcha;
            $action = $controller->createAction($actionID);
            if ($action === null) {
                throw new InvalidConfigException('Invalid CAPTCHA action ID: captcha');
            }

            if (!$action->validate($this->verificationCode, false)) {
                $this->addError($attribute, '验证码错误。');
            }
        }
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * @return boolean whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'username' => '用户名',
            'password' => '密码',
            'rememberMe' => '记住我',
            'verificationCode' => '验证码',
        ];
    }
}

