<?php

namespace app\modules\v1\behaviors;

use app\modules\v1\helpers\FileHelper;
use app\modules\v1\interfaces\FileInterface;
use app\modules\v1\models\Audiofile;
use app\modules\v1\models\Image;
use app\modules\v1\validators\ExistFileValidator;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\base\Behavior;
use yii\validators\Validator;
use Yii;

/**
 * Class FileBehavior
 * @package app\modules\v1\behaviors
 */
class FileActiveRecordBehavior extends Behavior {
	const SCENARIO_CHECK_FILE_EXIST = 'checkFileExist';

	public $fileSrcAttribute = 'file_src';
	public $fileIdAttribute = 'file_id';
	public $fileClass = Image::class;
	public $fileClassRelativeAttribute = 'id';

	/**
	 * @var boolean If `true` row at db will deleted on updated parent record
	 */
	public $deleteOnUpdate = true;
	/**
	 * @var boolean If `true` row at db will deleted on deleted parent record
	 */
	public $deleteOnDelete = true;

	/**
	 * @var \yii\validators\Validator[]
	 */
	protected $validators = []; // track references of appended validators

	private $_file;

	/**
	 * Get extra validation rules for file
	 *
	 * @return array
	 */
	protected function getFileValidationRules() {
		return [
			[$this->fileSrcAttribute, 'safe'],
			[$this->fileSrcAttribute, 'string'],
			[$this->fileSrcAttribute, ExistFileValidator::class, 'on' => self::SCENARIO_CHECK_FILE_EXIST],
			[
				$this->fileIdAttribute,
				'exist',
				'skipOnError' => true,
				'targetClass' => $this->fileClass,
				'targetAttribute' => [$this->fileIdAttribute => $this->fileClassRelativeAttribute]
			],
		];
	}

	/**
	 * @inheritdoc
	 *
	 * @throws InvalidConfigException
	 */
	public function attach($owner) {
		parent::attach($owner);

		if (!isset(class_implements($this->fileClass)[FileInterface::class])) {
			throw new InvalidConfigException($this->fileClass . ' must implement ' . FileInterface::class);
		}

		if (!is_subclass_of($this->fileClass, ActiveRecord::class)) {
			throw new InvalidConfigException($this->fileClass . ' must extends from ActiveRecord');
		}

		if (!is_subclass_of($owner, ActiveRecord::class)) {
			throw new InvalidConfigException($owner::className() . ' must extends from ActiveRecord');
		}

		$validators = $owner->validators;

		foreach ($this->getFileValidationRules() as $rule) {
			if ($rule instanceof Validator) {
				$validators->append($rule);
				$this->validators[] = $rule; // keep a reference in behavior
			} elseif (is_array($rule) && isset($rule[0], $rule[1])) {
				$validator = Validator::createValidator($rule[1], $owner, $rule[0], array_slice($rule, 2));
				$validators->append($validator);
				$this->validators[] = $validator; // keep a reference in behavior
			} else {
				throw new InvalidConfigException('Invalid validation rule: a rule must specify both attribute names and validator type.');
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function detach() {
		$ownerValidators = $this->owner->validators;
		$cleanValidators = [];
		foreach ($ownerValidators as $validator) {
			if (!in_array($validator, $this->validators)) {
				$cleanValidators[] = $validator;
			}
		}
		$ownerValidators->exchangeArray($cleanValidators);
	}

	/**
	 * @inheritdoc
	 */
	public function events() {
		return [
			ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
			ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdateOrInsert',
			ActiveRecord::EVENT_BEFORE_INSERT => 'beforeUpdateOrInsert',
			ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
			ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
		];
	}

	/**
	 * Before validate handle function
	 *
	 * @param $event
	 *
	 * @return bool
	 */
	public function beforeValidate($event) {
		/* @var $owner ActiveRecord */
		$owner = $event->sender;

		if (!empty($this->getFileSrc($owner))) {
			$file = $this->getPrivateFile();

			if (!$file->validate()) {
				$owner->addError($this->fileSrcAttribute, 'Error during validate file');
			}

			$owner->{$this->fileIdAttribute} = $file->id;
		}

		return true;
	}

	/**
	 * Before insert or update handle function
	 *
	 * @param $event
	 *
	 * @return bool
	 * @throws \Throwable
	 */
	public function beforeUpdateOrInsert($event) {
		/* @var $owner ActiveRecord */
		$owner = $event->sender;

		if (!empty($this->getFileSrc($owner))) {
			$transaction = Yii::$app->db->beginTransaction();

			try {
				$file = $this->getPrivateFile();

				if ($file->isNewRecord && !$file->save(false)) {
					$owner->addError($this->fileSrcAttribute, 'Error during save file');

					return false;
				}

				$owner->{$this->fileIdAttribute} = $file->id;

				$transaction->commit();
			} catch (\Exception $e) {
				$transaction->rollBack();
				throw $e;
			} catch (\Throwable $e) {
				$transaction->rollBack();
				throw $e;
			}
		}

		return true;
	}

	/**
	 * After update handle function
	 *
	 * @param $event
	 *
	 * @return bool
	 * @throws \Throwable
	 */
	public function afterUpdate($event) {
		$changedAttributes = $event->changedAttributes;

		if ($this->deleteOnUpdate && array_key_exists($this->fileIdAttribute, $changedAttributes) && !empty($changedAttributes[$this->fileIdAttribute])) {
			$this->deleteFileRecord($changedAttributes[$this->fileIdAttribute]);
		}

		return true;
	}

	/**
	 * After delete handle function
	 *
	 * @param $event
	 *
	 * @return bool
	 * @throws \Throwable
	 */
	public function afterDelete($event) {
		/* @var $owner ActiveRecord */
		$owner = $event->sender;

		if ($this->deleteOnDelete && !empty($this->getFileId($owner))) {
			$this->deleteFile($owner);
		}

		return true;
	}

	/**
	 * Get private file model
	 *
	 * @return ActiveRecord
	 */
	public function getPrivateFile()
	: ActiveRecord {
		if ($this->_file === null) {
			/* @var $fileClass FileInterface */
			/* @var $file ActiveRecord */
			$fileClass = $this->fileClass;
			$this->_file = $fileClass::initializeFromSrc($this->getFileSrc($this->owner));
		}

		return $this->_file;
	}

	/**
	 * Get file model
	 *
	 * @return mixed
	 */
	public function getFile() {
		return $this->owner->hasOne($this->fileClass, [$this->fileClassRelativeAttribute => $this->fileIdAttribute]);
	}

	/**
	 * Get file src from owner model
	 *
	 * @param ActiveRecord $owner
	 *
	 * @return mixed
	 */
	protected function getFileSrc(ActiveRecord $owner) {
		return $owner->{$this->fileSrcAttribute};
	}

	/**
	 * Get file id from owner model
	 *
	 * @param ActiveRecord $owner
	 * @param bool $old
	 *
	 * @return mixed
	 */
	protected function getFileId(ActiveRecord $owner, $old = false) {
		return ($old === true) ? $owner->getOldAttribute($this->fileIdAttribute) : $owner->{$this->fileIdAttribute};
	}

	/**
	 * Delete file for owner model
	 *
	 * @param ActiveRecord $owner
	 * @param bool $old
	 *
	 * @return int
	 */
	protected function deleteFile(ActiveRecord $owner, $old = false) {
		/* @var $fileClass ActiveRecord */
		$fileClass = $this->fileClass;

		return $this->deleteFileRecord($this->getFileId($owner, $old));
	}

	/**
	 * Delete file record at db
	 *
	 * @param $fileId
	 *
	 * @return int
	 */
	protected function deleteFileRecord($fileId) {
		/* @var $fileClass ActiveRecord */
		$fileClass = $this->fileClass;

		return $fileClass::deleteAll([$this->fileClassRelativeAttribute => $fileId]);
	}
}