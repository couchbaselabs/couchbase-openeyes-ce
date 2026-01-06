<?php

/**
 * Class EffectAdminController
 *
 * Admin controller class for EffectAdminController
 */
class EffectAdminController extends BaseAdminController
{
    /**
     * @var string
     */
    public $layout = 'application.modules.Genetics.views.layouts.genetics';

    protected $itemsPerPage = 100;

    public function accessRules()
    {
        return array(array('allow', 'roles' => array('Genetics Admin')));
    }

    /**
     * Lists OphInGeneticresults_Test_Effect.
     *
     * @throws CHttpException
     */
    public function actionList()
    {
        $admin = new Admin(OphInGeneticresults_Test_Effect::model(), $this);
        $admin->setModelDisplayName('Genetic Results Effect');
        $admin->setListFields(array(
            'id',
            'name',
        ));
        $admin->searchAll();
        $admin->getSearch()->setItemsPerPage($this->itemsPerPage);
        $admin->getSearch()->setDefaultResults(false);
        $admin->listModel();
    }

    /**
     * Edits or adds a Genetic Results Effect.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEdit($id = false)
    {
        $admin = new Admin(OphInGeneticresults_Test_Effect::model(), $this);
        if ($id) {
            $admin->setModelId($id);
        }
        $admin->setModelDisplayName('Genetic Results Effect');
        $admin->setEditFields(array(
            'referer' => 'referer',
            'name' => 'text',
        ));

        $admin->setCustomCancelURL(Yii::app()->request->getUrlReferrer());

        $valid = $admin->editModel(false);

        if (Yii::app()->request->isPostRequest) {
            if ($valid) {
                Yii::app()->user->setFlash('success', "Genetic Results Effect Saved");
                $this->redirect('/' . $this->module->id . '/' .$this->id . '/list');
            } else {
                $admin->render($admin->getEditTemplate(), array('admin' => $admin, 'errors' => $admin->getModel()->getErrors()));
            }
        }
    }

    /**
     * Deletes rows for the model.
     *
     * @param null $id
     *
     * @throws CHttpException
     */
    public function actionDelete($id = null)
    {
        $modelClass = 'OphInGeneticresults_Test_Effect';
        
        // Handle GET requests with ID in URL parameter
        if (!Yii::app()->request->isPostRequest) {
            if (is_null($id)) {
                throw new CHttpException(400, 'Invalid request: no ID provided');
            }
            $ids = array($id);
        } else {
            // Handle POST requests with multiple IDs
            $modelData = Yii::app()->request->getPost($modelClass);
            if (is_null($modelData) || !isset($modelData['id'])) {
                throw new CHttpException(400, 'Invalid request: no effect IDs provided');
            }
            $ids = $modelData['id'];
        }

        $response = 1;
        $model = OphInGeneticresults_Test_Effect::model();
        foreach ($ids as $itemId) {
            if (is_null($itemId) || empty($itemId)) {
                continue;
            }
            
            try {
                $effect = $model->findByPk($itemId);
                if ($effect) {
                    $attributes = $effect->getAttributes();
                    if (isset($effect->active)) {
                        $effect->active = 0;
                        if (!$effect->save()) {
                            $response = 0;
                        }
                    } else {
                        if (!$effect->delete()) {
                            $response = 0;
                        }
                    }
                    if ($response == 1) {
                        Audit::add(get_class($effect), 'delete', serialize($attributes), get_class($effect) . ' deleted');
                    }
                } else {
                    // Effect not found, treat as success
                    Yii::log('Effect ' . $itemId . ' not found (may have already been deleted)', CLogger::LEVEL_INFO, 'application.delete');
                }
            } catch (Exception $e) {
                Yii::log('Exception deleting effect ' . $itemId . ': ' . $e->getMessage() . ' (' . get_class($e) . ')', CLogger::LEVEL_ERROR, 'application.delete');
                $response = 0;
            }
        }

        echo $response;
    }
}
