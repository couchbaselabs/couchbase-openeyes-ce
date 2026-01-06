<?php

/**
 * Class ExternalSourceAdminController
 *
 * Admin controller class for ExternalSourceAdminController
 */
class ExternalSourceAdminController extends BaseAdminController
{
    /**
     * @var string
     */
    public $layout = '//../modules/genetics/views/layouts/genetics';

    protected $itemsPerPage = 100;

    public function accessRules()
    {
        return array(array('allow', 'roles' => array('Genetics Admin')));
    }

    /**
     * Lists OphInGeneticresults_External_Source.
     *
     * @throws CHttpException
     */
    public function actionList()
    {
        $admin = new Admin(OphInGeneticresults_External_Source::model(), $this);
        $admin->setModelDisplayName('External Source');
        $admin->setListFields(array(
            'id',
            'name',
        ));
        $admin->searchAll();
        $admin->getSearch()->setItemsPerPage($this->itemsPerPage);
        $admin->listModel();
    }

    /**
     * Edits or adds a Genetic Test Effect.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEdit($id = false)
    {
        $admin = new Admin(OphInGeneticresults_External_Source::model(), $this);
        if ($id) {
            $admin->setModelId($id);
        }
        $admin->setModelDisplayName('External Source');
        $admin->setEditFields(array(
            'name' => 'text',
        ));
        $admin->editModel();
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
        $modelClass = 'OphInGeneticresults_External_Source';
        
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
                throw new CHttpException(400, 'Invalid request: no source IDs provided');
            }
            $ids = $modelData['id'];
        }

        $response = 1;
        $model = OphInGeneticresults_External_Source::model();
        foreach ($ids as $itemId) {
            if (is_null($itemId) || empty($itemId)) {
                continue;
            }
            
            try {
                $source = $model->findByPk($itemId);
                if ($source) {
                    $attributes = $source->getAttributes();
                    if (isset($source->active)) {
                        $source->active = 0;
                        if (!$source->save()) {
                            $response = 0;
                        }
                    } else {
                        if (!$source->delete()) {
                            $response = 0;
                        }
                    }
                    if ($response == 1) {
                        Audit::add(get_class($source), 'delete', serialize($attributes), get_class($source) . ' deleted');
                    }
                } else {
                    // Source not found, treat as success
                    Yii::log('Source ' . $itemId . ' not found (may have already been deleted)', CLogger::LEVEL_INFO, 'application.delete');
                }
            } catch (Exception $e) {
                Yii::log('Exception deleting source ' . $itemId . ': ' . $e->getMessage() . ' (' . get_class($e) . ')', CLogger::LEVEL_ERROR, 'application.delete');
                $response = 0;
            }
        }

        echo $response;
    }
}
