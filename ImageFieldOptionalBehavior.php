<?php
namespace noneedinmagic\imagefield;

use Yii;

class ImageFieldOptionalBehavior extends ImageFieldBehavior
{
	public function beforeValidate($event){
		parent::beforeValidate($event);
		return TRUE;
	}

	public function beforeSave($event){
        // return empty($this->owner->{$this->fileAttribute}) || parent::beforeSave($event);
		return empty($this->_getFile()) || parent::beforeSave($event);
	}

	public function afterDelete($event){
        // if(!empty($this->owner->{$this->filenameAttribute})){
		if(!empty($this->_getFilename())){
			parent::afterDelete($event);
		}
	}

    public function imageUrl($options = [])
    {
        // return empty($this->owner->{$this->filenameAttribute}) ? NULL : parent::imageUrl($options);
        return empty($this->_getFilename()) ? NULL : parent::imageUrl($options);
    }

    public function imageOriginalPath()
    {
        // return empty($this->owner->{$this->filenameAttribute}) ? NULL : parent::imageOriginalPath();
        return empty($this->_getFilename()) ? NULL : parent::imageOriginalPath();
    }

    protected function imageCachePath()
    {
        // return empty($this->owner->{$this->filenameAttribute}) ? NULL : parent::imageCachePath();
        return empty($this->_getFilename()) ? NULL : parent::imageCachePath();
    }

    public function dropCache()
    {
        // if(!empty($this->owner->{$this->filenameAttribute})){
		if(!empty($this->_getFilename())){
			parent::dropCache();
		}
    }

    public function imagePath($options = []){
        // return empty($this->owner->{$this->filenameAttribute}) ? NULL : parent::imagePath($options);
        return empty($this->_getFilename()) ? NULL : parent::imagePath($options);
    }
}
