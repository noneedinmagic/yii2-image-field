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
	public $fileAttribute = 'file';
	public $filenameAttribute = 'filename';
	public $filenameLength = 16;

	public $imagineClass = '\Imagine\Gd\Imagine';
	public $imageStorePath;
	public $imageCachePath;
	public $imageDefaultUrl;

	public function events(){
		return [
			ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
			ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
			ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
			ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
		];
	}

	public function beforeValidate($event){
		if(empty($this->owner->{$this->fileAttribute})){
            if (Yii::$app->request->isPost) {
                $this->owner->{$this->fileAttribute} = UploadedFile::getInstance($this->owner, $this->fileAttribute);
                if($this->owner->{$this->fileAttribute}){
                	if(!empty($this->owner->{$this->filenameAttribute})){
                		$this->afterDelete(NULL);
                	}
                	$this->owner->{$this->filenameAttribute} = Yii::$app->security->generateRandomString($this->filenameLength) . '.' . $this->owner->{$this->fileAttribute}->extension;
                	return TRUE;
                }
            }
		} elseif($this->owner->{$this->fileAttribute} instanceof UploadedFile){
            if(!empty($this->owner->{$this->filenameAttribute})){
                $this->afterDelete(NULL);
            }
            $this->owner->{$this->filenameAttribute} = Yii::$app->security->generateRandomString($this->filenameLength) . '.' . $this->owner->{$this->fileAttribute}->extension;
            return TRUE;
        }
		return FALSE;
	}

	public function beforeSave($event){
        if (!empty($this->owner->{$this->fileAttribute}) && BaseFileHelper::createDirectory(Yii::getAlias($this->imageStorePath))) {
            return $this->owner->{$this->fileAttribute}->saveAs($this->imageOriginalPath());
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
        return Yii::getAlias($this->imageStorePath . '/' . $this->owner->{$this->filenameAttribute});
    }

    protected function imageCachePath()
    {
        return Yii::getAlias($this->imageCachePath .'/'. substr($this->owner->{$this->filenameAttribute}, 0, 1) .'/'. substr($this->owner->{$this->filenameAttribute}, 0, 2) .'/'. $this->owner->{$this->filenameAttribute} .'/');
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
