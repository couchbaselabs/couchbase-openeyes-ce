<?php

/**
 * Class MethodAdminController
 *
 * Admin controller class for MethodAdminController
 */
class MethodAdminController extends BaseAdminController
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
     * Lists OphInGeneticresults_Test_Method.
     *
     * @throws CHttpException
     */
    public function actionList()
    {
        $admin = new Admin(OphInGeneticresults_Test_Method::model(), $this);
        $admin->setModelDisplayName('Genetic Results Method');
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
     * Edits or adds a Genetic Results Method.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEdit($id = false)
    {
        $admin = new Admin(OphInGeneticresults_Test_Method::model(), $this);
        if ($id) {
            $admin->setModelId($id);
        }
        $admin->setModelDisplayName('Genetic Results Method');
        $admin->setEditFields(array(
            'referer' => 'referer',
            'name' => 'text',
        ));

        $admin->setCustomCancelURL(Yii::app()->request->getUrlReferrer());

        $valid = $admin->editModel(false);

        if (Yii::app()->request->isPostRequest) {
            if ($valid) {
                Yii::app()->user->setFlash('success', "Genetic Results Method Saved");
                $this->redirect('/OphInGeneticresults/methodAdmin/list');
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
        $modelClass = 'OphInGeneticresults_Test_Method';
        
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
                throw new CHttpException(400, 'Invalid request: no method IDs provided');
            }
            $ids = $modelData['id'];
        }

        $response = 1;
        $model = OphInGeneticresults_Test_Method::model();
        foreach ($ids as $itemId) {
            if (is_null($itemId) || empty($itemId)) {
                continue;
            }
            
            try {
                $method = $model->findByPk($itemId);
                if ($method) {
                    $attributes = $method->getAttributes();
                    if (isset($method->active)) {
                        $method->active = 0;
                        if (!$method->save()) {
                            $response = 0;
                        }
                    } else {
                        if (!$method->delete()) {
                            $response = 0;
                        }
                    }
                    if ($response == 1) {
                        Audit::add(get_class($method), 'delete', serialize($attributes), get_class($method) . ' deleted');
                    }
                } else {
                    // Method not found, treat as success
                    Yii::log('Method ' . $itemId . ' not found (may have already been deleted)', CLogger::LEVEL_INFO, 'application.delete');
                }
            } catch (Exception $e) {
                Yii::log('Exception deleting method ' . $itemId . ': ' . $e->getMessage() . ' (' . get_class($e) . ')', CLogger::LEVEL_ERROR, 'application.delete');
                $response = 0;
            }
        }

        echo $response;
    }
}
