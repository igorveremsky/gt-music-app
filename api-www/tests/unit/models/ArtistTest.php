<?php

namespace tests\models;

use app\modules\v1\behaviors\FileActiveRecordBehavior;
use app\modules\v1\models\Artist;
use app\modules\v1\models\Image;
use yii\db\ActiveRecord;

/**
 * Class ArtistTest
 * @package tests\models
 *
 * @property \UnitTester $tester
 * @property ActiveRecord $modelClass
 */
class ArtistTest extends \Codeception\Test\Unit {
	protected $modelClass = Artist::class;
	protected $initData = [
		'name' => 'artist',
		'type' => 's',
		'avatar_img_src' => 'http://reynoldsandreyner.com/wp-content/uploads/chernigivske-website-avatars-01v-675x645.jpg'
	];
	protected $forSaveData = [
		'name' => 'new artist',
		'type' => 's',
		'avatar_img_src' => 'http://reynoldsandreyner.com/wp-content/uploads/the-book-2018-03-updated.jpg'
	];
	protected $forUpdateData = [
		'name' => 'update artist',
		'type' => 'g',
		'avatar_img_src' => 'http://reynoldsandreyner.com/wp-content/uploads/the-book-2018-04-updated.jpg'
	];

	protected $id;

	function _before() {
		// preparing a user, inserting user record to database
		$this->id = $this->tester->haveRecord($this->modelClass, $this->initData);
	}

	public function testValidation() {
		/* @var $model Artist */
		$model = new $this->modelClass;

		$model->name = null;
		$this->tester->assertFalse($model->validate('name'));

		$model->name = 'artist';
		$this->tester->assertFalse($model->validate('name'));

		$model->name = 'test';
		$this->tester->assertTrue($model->validate('name'));

		$model->type = null;
		$this->tester->assertFalse($model->validate('type'));

		$model->type = Artist::TYPE_GROUP;
		$this->tester->assertTrue($model->validate('type'));

		$model->type = Artist::TYPE_SINGLE;
		$this->tester->assertTrue($model->validate('type'));

		$model->avatar_img_src = 'test';
		$this->tester->assertTrue($model->validate('avatar_img_src'));

		$model->setScenario(FileActiveRecordBehavior::SCENARIO_CHECK_FILE_EXIST);
		$model->avatar_img_src = 'test';
		$this->tester->assertFalse($model->validate('avatar_img_src'));

		$model->avatar_img_src = 'http://reynoldsandreyner.com/wp-content/uploads/the-book-2018-05-updated-1350x1290.jpg';
		$this->tester->assertTrue($model->validate('avatar_img_src'));
	}

	/**
	 * @depends testValidation
	 */
	public function testCreate() {
		/* @var $model ActiveRecord */
		$model = new $this->modelClass();
		$this->setModelAttributes($model, $this->forSaveData);
		$model->save(false);
		$imageSrc = $this->forSaveData['avatar_img_src'];
		$this->tester->seeRecord(Image::class, ['file_src' => $imageSrc]);
		$image = $this->tester->grabRecord(Image::class, ['file_src' => $imageSrc]);
		$savedData = array_merge(['avatar_img_id' => $image['id']], $this->forSaveData);
		unset($savedData['avatar_img_src']);
		$this->tester->seeRecord($this->modelClass, $savedData);
	}

	public function testUpdate() {
		$model = $this->modelClass::findOne($this->id);
		$this->tester->assertNotNull($model);
		$this->setModelAttributes($model, $this->forUpdateData);
		$model->save();
		$updatedData = array_merge(['id' => $this->id], $this->forUpdateData);
		$imageSrc = $updatedData['avatar_img_src'];
		unset($updatedData['avatar_img_src']);
		$this->tester->seeRecord(Image::class, ['file_src' => $imageSrc]);
		$image = $this->tester->grabRecord(Image::class, ['file_src' => $imageSrc]);
		$this->tester->dontSeeRecord(Image::class, ['file_src' => $this->initData['avatar_img_src']]);
		$updatedData['avatar_img_id'] = $image['id'];
		$this->tester->seeRecord($this->modelClass, $updatedData);
		unset($this->initData['avatar_img_src']);
		$this->tester->dontSeeRecord($this->modelClass, $this->initData);
	}

	public function testDelete() {
		$model = $this->modelClass::findOne($this->id);
		$this->tester->assertNotNull($model);
		$model->delete();
		$deletedData = array_merge(['id' => $this->id], $this->initData);
		$this->tester->dontSeeRecord(Image::class, ['file_src' => $deletedData['avatar_img_src']]);
		unset($deletedData['avatar_img_src']);
		$this->tester->dontSeeRecord($this->modelClass, $deletedData);
	}

	protected function setModelAttributes(ActiveRecord $model, $data) {
		foreach ($data as $attribute => $value) {
			$model->$attribute = $value;
		}
	}
}