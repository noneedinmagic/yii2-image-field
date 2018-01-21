<?php
namespace noneedinmagic\imagefield;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\helpers\BaseFileHelper;
use yii\helpers\Url;

class ImageFieldBehavior extends AttributeBehavior
{
	public $imagineClass = '\Imagine\Gd\Imagine';

	public $fileAttribute = 'file';
	public $filenameAttribute = 'filename';
	public $filenameLength = 16;

	public $multiple = false;
	public $multipleSeparator = '|';

	public $optional = false;

	public $imageStorePath;
	public $imageCachePath;
	public $imageDefaultUrl;

	protected $_file = null;
	protected function _getFile(){
		// return $this->owner->{$this->fileAttribute};
		return $this->_file;
	}
	protected function _setFile($value){
		// $this->owner->{$this->fileAttribute} = $value;
		$this->_file = $value;
	}
	public function __get($name){
		return $name === $this->fileAttribute ? $this->_getFile() : parent::__get($name);
	}
	public function __set($name, $value){
		return $name === $this->fileAttribute ? $this->_setFile($value) : parent::__set($name, $value);
	}
	public function canGetProperty($name, $checkVars = true){
		return $name === $this->fileAttribute || parent::canGetProperty($name, $checkVars);
	}
	public function canSetProperty($name, $checkVars = true){
		return $name === $this->fileAttribute || parent::canSetProperty($name, $checkVars);
	}

	protected function _getFilename(){
		return $this->owner->{$this->filenameAttribute};
	}
	protected function _setFilename($value){
		$this->owner->{$this->filenameAttribute} = $value;
	}

	public function events(){
		return [
			ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
			ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
			ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
			ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
		];
	}

	public function beforeValidate($event){
		if(empty($this->_getFile()) && Yii::$app->request->isPost){
			$this->_setFile(UploadedFile::getInstance($this->owner, $this->fileAttribute));
		}

		if($this->_getFile() instanceof UploadedFile){
			if(!empty($this->_getFilename())){
				$this->afterDelete(NULL);
			}
			$this->_setFilename(Yii::$app->security->generateRandomString($this->filenameLength) . '.' . $this->_getFile()->extension);
			return TRUE;
		}
		return FALSE;
	}

	public function beforeSave($event){
		if (!empty($this->_getFile()) && BaseFileHelper::createDirectory(Yii::getAlias($this->imageStorePath))) {
			return $this->_getFile()->saveAs($this->imageOriginalPath());
		} else {
			return false;
		}
	}

	public function afterDelete($event){
		$this->dropCache();
		if(is_file($this->imageOriginalPath())){
			unlink($this->imageOriginalPath());
		}
	}

	public function imageUrl($options = [])
	{
		return Url::to(str_replace(
			Yii::getAlias('@webroot'),
			Yii::getAlias('@web'),
			Yii::getAlias($this->imagePath($options))));
	}

	public function imageOriginalPath()
	{
		return Yii::getAlias($this->imageStorePath . '/' . $this->_getFilename());
	}

	protected function imageCachePath()
	{
		return Yii::getAlias($this->imageCachePath .'/'. substr($this->_getFilename(), 0, 1) .'/'. substr($this->_getFilename(), 0, 2) .'/'. $this->_getFilename() .'/');
	}

	public function dropCache()
	{
		BaseFileHelper::removeDirectory($this->imageCachePath());
		$levels = BaseFileHelper::normalizePath($this->imageCachePath() . '../..');
		if(is_dir($levels = BaseFileHelper::normalizePath($this->imageCachePath() . '../..')) && !BaseFileHelper::findFiles($levels)){
			BaseFileHelper::removeDirectory($levels);
		} elseif(is_dir($levels = BaseFileHelper::normalizePath($this->imageCachePath() . '..')) && !BaseFileHelper::findFiles($levels)){
			BaseFileHelper::removeDirectory($levels);
		}
	}

	//TODO: remove $normalize
	//TODO: edit $fullresult
	public function imagePath($options = [])
	{
		if(!is_file($this->imageOriginalPath())){
			return NULL;
		}
		if(!empty($options['original'])){
			return $this->imageOriginalPath();
		}

		$options = array_merge([
			'width' => null,
			'height' => null,
			'preserveAspectRatio' => true,
			'inset' => false,
			'format' => 'jpg',
			'jpeg_quality' => 80,
			'png_compression_level' => 7,
			'rewrite' => false,
			'path' => $this->imageCachePath(),
			'filename' => '{width}x{height}.{ext}',
		], $options);

		$filename = str_replace(['{width}', '{height}', '{ext}'], [
			(empty($options['width']) ? '' : $options['width']),        //width
			(empty($options['height']) ? '' : $options['height']),      //height
			(empty($options['format']) ? '' : $options['format']),      //format
			// ()
		], $options['filename']);

		$fullpath = BaseFileHelper::normalizePath($options['path']);
		$fullname = BaseFileHelper::normalizePath($fullpath .'/'. $filename);

		if($options['rewrite'] || !file_exists($fullname)){
			$image = (new $this->imagineClass)->open($this->imageOriginalPath());

			$box = $image->getSize();
			if(empty($options['width'])){
				if($options['height']){
					$box = $box->scale($options['height'] / $box->getHeight());
				}
			} elseif(empty($options['height'])){
				$box = $box->scale($options['width'] / $box->getWidth());
			} else {
				$box = new \Imagine\Image\Box($options['width'], $options['height']);
			}

			$image = $image->thumbnail($box);

			BaseFileHelper::createDirectory($fullpath);

			$image->save($fullname);
		}

		return BaseFileHelper::normalizePath($fullname, '/');;
	}
}
