<?php
namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;
use yii\helpers\Html;
use common\models\Tool;
class RegistrationForm extends Model
{
	public $username;
	public $password;
	public $repeat_password;
	public $captcha;

  //EFX estendo modulo registrazione con dati per la user_details
	public $nome;
	public $cognome;
	public $ragioneSociale;
	public $codiceFiscale;
	public $partitaIva;
	public $idProfilo;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		$rules = [
			['captcha', 'captcha', 'captchaAction'=>'/user-management/auth/captcha'],

			[['username', 'password', 'repeat_password', 'captcha', 'nome', 'cognome','idProfilo','codiceFiscale'], 'required'],
			[['username', 'password', 'repeat_password', 'nome', 'cognome',
			  'ragioneSociale', 'codiceFiscale', 'partitaIva'], 'trim'],

			['username', 'unique',
				'targetClass'     => 'webvimark\modules\UserManagement\models\User',
				'targetAttribute' => 'username',
			],

			['username', 'purgeXSS'],

			['password', 'string', 'max' => 255],
			['nome', 'string', 'max' => 255],
			['cognome', 'string', 'max' => 255],
			['ragioneSociale', 'string', 'max' => 255],
			['codiceFiscale', 'string', 'max' => 255],
			['partitaIva', 'string', 'max' => 255],

			['repeat_password', 'compare', 'compareAttribute'=>'password'],
		];

		if ( Yii::$app->getModule('user-management')->useEmailAsLogin )
		{
			$rules[] = ['username', 'email'];
		}
		else
		{
			$rules[] = ['username', 'string', 'max' => 50];

			$rules[] = ['username', 'match', 'pattern'=>Yii::$app->getModule('user-management')->registrationRegexp];
			$rules[] = ['username', 'match', 'not'=>true, 'pattern'=>Yii::$app->getModule('user-management')->registrationBlackRegexp];
		}

		return $rules;
	}

	/**
	 * Remove possible XSS stuff
	 *
	 * @param $attribute
	 */
	public function purgeXSS($attribute)
	{
		$this->$attribute = Html::encode($this->$attribute);
	}

	/**
	 * @return array
	 */
	public function attributeLabels()
	{
		return [
			'username'        => Yii::$app->getModule('user-management')->useEmailAsLogin ? 'E-mail' : UserManagementModule::t('front', 'Login'),
			'password'        => Yii::t('yii','Password'),
			'repeat_password' => Yii::t('yii','Repeat password'),
			'captcha'         => UserManagementModule::t('front', 'Captcha'),
			'nome'         		=>  Yii::t('yii','Name'),
			'cognome'         =>  Yii::t('yii','Surname'),
			'ragioneSociale'  =>  Yii::t('yii','Company name'),//
			'codiceFiscale'   =>  Yii::t('yii','Tax code'), //
			'partitaIva'      =>  Yii::t('yii','VAT number'), //
			'idProfilo'      =>  Yii::t('yii','Category'), //



		];
	}

	/**
	 * @param bool $performValidation
	 *
	 * @return bool|User
	 */
	public function registerUser($performValidation = true)
	{
		if ( $performValidation AND !$this->validate() )
		{
			return false;
		}

		$user = new User();
		$user->password = $this->password;

		if ( Yii::$app->getModule('user-management')->useEmailAsLogin )
		{
			$user->email = $this->username;

			// If email confirmation required then we save user with "inactive" status
			// and without username (username will be filled with email value after confirmation)
			if ( Yii::$app->getModule('user-management')->emailConfirmationRequired )
			{
				$user->status = User::STATUS_INACTIVE;
				$user->generateConfirmationToken();
				$user->save(false);

				$this->saveProfile($user);

				if ( $this->sendConfirmationEmail($user) )
				{
					return $user;
				}
				else
				{
					$this->addError('username', UserManagementModule::t('front', 'Could not send confirmation email'));
				}
			}
			else
			{
				$user->username = $this->username;
			}
		}
		else
		{
			$user->username = $this->username;
		}


		if ( $user->save() )
		{
			$this->saveProfile($user);



			return $user;
		}
		else
		{
			$this->addError('username', UserManagementModule::t('front', 'Login has been taken'));
		}
	}

	/**
	 * Implement your own logic if you have user profile and save some there after registration
	 *
	 * @param User $user
	 */
	protected function saveProfile($user)
	{
		//EFX salvataggio user details
		\common\models\Userdetails::$VALIDATION_TYPE="WORLD";
		$userDetails = \common\models\Userdetails::find()->where(['=', "id", $user->id])->one();

		if (empty($userDetails)) $userDetails = new \common\models\Userdetails;

		$userDetails->id = $user->id;
		$userDetails->mail = $user->email;
		$userDetails->nome = $this->nome;
		$userDetails->cognome = $this->cognome;
		$userDetails->ragioneSociale = $this->ragioneSociale;
		$userDetails->codiceFiscale = $this->codiceFiscale;
		$userDetails->partitaIva = $this->partitaIva;
		$userDetails->idProfilo = $this->idProfilo;
    Tool::logValidate($userDetails);
		$userDetails->save();

		\backend\controllers\ThisSiteController::insertCliente($this, $userDetails);

	}


	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	protected function sendConfirmationEmail($user)
	{
		return Yii::$app->mailer->compose(Yii::$app->getModule('user-management')->mailerOptions['registrationFormViewFile'], ['user' => $user])
			->setFrom(Yii::$app->getModule('user-management')->mailerOptions['from'])
			->setTo($user->email)
			->setSubject(UserManagementModule::t('front', 'E-mail confirmation for') . ' ' . Yii::$app->name)
			->send();
	}

	/**
	 * Check received confirmation token and if user found - activate it, set username, roles and log him in
	 *
	 * @param string $token
	 *
	 * @return bool|User
	 */
	public function checkConfirmationToken($token)
	{
		$user = User::findInactiveByConfirmationToken($token);

		if ( $user )
		{
			$user->username = $user->email;
			$user->status = User::STATUS_ACTIVE;
			$user->email_confirmed = 1;
			$user->removeConfirmationToken();
			$user->save(false);

			$roles = (array)Yii::$app->getModule('user-management')->rolesAfterRegistration;

			foreach ($roles as $role)
			{
				User::assignRole($user->id, $role);
			}

			Yii::$app->user->login($user);

			return $user;
		}

		return false;
	}
}
