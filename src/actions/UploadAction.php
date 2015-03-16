<?php
namespace trntv\filekit\actions;

use trntv\filekit\events\UploadEvent;
use trntv\filekit\File;
use League\Flysystem\File as FlysystemFile;
use yii\base\DynamicModel;
use yii\helpers\Url;
use yii\web\Response;
use yii\web\UploadedFile;

/**
* Class UploadAction
* public function actions(){
*   return [
*           'upload'=>[
*               'class'=>'trntv\filekit\actions\UploadAction',
*           ]
*       ];
*   }
*/
class UploadAction extends BaseAction
{

    const EVENT_AFTER_SAVE = 'afterSave';

    /**
     * @var string
     */
    public $fileparam = 'file';

    /**
     * @var bool
     */
    public $disableCsrf = true;

    /**
     * @var string
     */
    public $responseFormat = Response::FORMAT_JSON;
    /**
     * @var string
     */
    public $responsePathParam = 'path';
    /**
     * @var string
     */
    public $responseBaseUrlParam = 'base_url';
    /**
     * @var string
     */
    public $responseDeleteUrlParam = 'delete_url';
    /**
     * @var string
     */
    public $responseMimeTypeParam = 'type';
    /**
     * @var string
     */
    public $responseNameParam = 'name';
    /**
     * @var string
     */
    public $responseSizeParam = 'size';
    /**
     * @var string
     */
    public $deleteRoute = 'delete';

    /**
     * @var array
     */
    public $validationRules;

    /**
     *
     */
    public function init()
    {
        \Yii::$app->response->format = $this->responseFormat;

        if (\Yii::$app->request->get('fileparam')) {
            $this->fileparam = \Yii::$app->request->get('fileparam');
        }

        if ($this->disableCsrf) {
            \Yii::$app->request->enableCsrfValidation = false;
        }
    }

    /**
     * @return array
     * @throws \HttpException
     */
    public function run()
    {
        $result = [];
        $uploadedFiles = UploadedFile::getInstancesByName($this->fileparam);

        foreach ($uploadedFiles as $uploadedFile) {
            /* @var \yii\web\UploadedFile $uploadedFile */
            $output = [
                $this->responseNameParam => $uploadedFile->getBaseName(),
                $this->responseMimeTypeParam => $uploadedFile->type,
                $this->responseSizeParam => $uploadedFile->size,
                $this->responseBaseUrlParam =>  $this->getFileStorage()->baseUrl
            ];
            if ($uploadedFile->error === UPLOAD_ERR_OK) {
                $validationModel = new DynamicModel(['file'], $this->validationRules);
                $validationModel->file = $uploadedFile;
                if ($validationModel->validate()) {
                    $path = $this->getFileStorage()->save(File::create($uploadedFile));

                    if ($path) {
                        $output[$this->responsePathParam] = $path;
                        $output[$this->responseDeleteUrlParam] = Url::to([$this->deleteRoute, 'path' => $path]);
                        $paths = \Yii::$app->session->get($this->sessionKey, []);
                        $paths[] = $path;
                        \Yii::$app->session->set($this->sessionKey, $paths);
                        $this->afterSave($output, new FlysystemFile($this->getFileStorage()->getFilesystem(), $path));

                    } else {
                        $output['error'] = true;
                        $output['errors'] = [];
                    }

                } else {
                    $output['error'] = true;
                    $output['errors'] = $validationModel->errors;
                }
            } else {
                $output['error'] = true;
                $output['errors'] = $uploadedFile->error;
            }

            $result['files'][] = $output;
        }
        return $result;
    }

    public function afterSave($file)
    {
        $this->trigger(self::EVENT_AFTER_SAVE, new UploadEvent([
            'file' => $file
        ]));
    }
}
