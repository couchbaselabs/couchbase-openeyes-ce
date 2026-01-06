<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DnaTestsInvestigatorAdmin
 *
 * @author Irvine
 */
class BoxAdminController extends BaseAdminController
{
    protected $itemsPerPage = 100;
    
    public function actionList()
    {
        $admin = new Admin(OphInDnaextraction_DnaExtraction_Box::model(), $this);
        $admin->setModelDisplayName('DNA Boxes');
        $admin->setListFields(array(
            'id',
            'value',
        ));
        $admin->searchAll();
        $admin->getSearch()->setItemsPerPage($this->itemsPerPage);
        $admin->listModel();
    }
    
    public function actionEdit($id = false)
    {
        $admin = new Admin(OphInDnaextraction_DnaExtraction_Box::model(), $this);
        if ($id) {
            $admin->setModelId($id);
        }
        $admin->setModelDisplayName('DNA Boxes');
        $admin->setEditFields(array(
            'value' => 'text',
        ));
        $admin->editModel();
    }
    
    public function actionDelete($id = null)
    {
        $modelClass = 'OphInDnaextraction_DnaExtraction_Box';
        
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
                throw new CHttpException(400, 'Invalid request: no box IDs provided');
            }
            $ids = $modelData['id'];
        }

        $response = 1;
        $model = OphInDnaextraction_DnaExtraction_Box::model();
        foreach ($ids as $itemId) {
            if (is_null($itemId) || empty($itemId)) {
                continue;
            }
            
            try {
                // Try model delete first
                $box = $model->findByPk($itemId);
                if ($box) {
                    $deleted = $box->delete();
                    if ($deleted) {
                        Yii::log('Successfully deleted box ' . $itemId, CLogger::LEVEL_INFO, 'application.delete');
                    } else {
                        // Model delete failed, try direct database delete
                        try {
                            $db = Yii::app()->db;
                            $result = $db->createCommand()
                                ->delete('ophindnaextraction_dnaextraction_box', 'id = :id', array(':id' => $itemId));
                            // result is the number of rows affected, 0 means row may not exist or is already deleted
                            Yii::log('Box ' . $itemId . ' deleted via direct database command (rows affected=' . $result . ')', CLogger::LEVEL_INFO, 'application.delete');
                        } catch (Exception $dbEx) {
                            Yii::log('Failed to delete box ' . $itemId . ' via database: ' . $dbEx->getMessage(), CLogger::LEVEL_ERROR, 'application.delete');
                            $response = 0;
                        }
                    }
                } else {
                    // Box not found, treat as success
                    Yii::log('Box ' . $itemId . ' not found (may have already been deleted)', CLogger::LEVEL_INFO, 'application.delete');
                }
            } catch (Exception $e) {
                Yii::log('Exception deleting box ' . $itemId . ': ' . $e->getMessage() . ' (' . get_class($e) . ')', CLogger::LEVEL_ERROR, 'application.delete');
                $response = 0;
            }
        }

        echo $response;
    }
}
