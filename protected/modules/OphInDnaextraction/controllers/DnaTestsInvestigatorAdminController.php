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
class DnaTestsInvestigatorAdminController extends BaseAdminController
{
    protected $itemsPerPage = 100;

    public function actionList()
    {
        $admin = new Admin(OphInDnaextraction_DnaTests_Investigator::model(), $this);
        $admin->setModelDisplayName('DNA Investigators');
        $admin->setListFields(array(
            'id',
            'name',
            'display_order',
        ));
        $admin->searchAll();
        $admin->getSearch()->setItemsPerPage($this->itemsPerPage);
        $admin->listModel();
    }
    
    public function actionEdit($id = false)
    {
        $admin = new Admin(OphInDnaextraction_DnaTests_Investigator::model(), $this);
        if ($id) {
            $admin->setModelId($id);
        }
        $admin->setModelDisplayName('DNA Investigators');
        $admin->setEditFields(array(
            'name' => 'text',
        ));
        $admin->editModel();
    }
    
    public function actionDelete()
    {
        $admin = new Admin(OphInDnaextraction_DnaTests_Investigator::model(), $this);
        $admin->deleteModel();
    }

    public function actionSort()
    {
        $admin = new Admin(OphInDnaextraction_DnaTests_Investigator::model(), $this);
        $admin->setModelDisplayName('DNA Investigators');
        $admin->setListFields(array(
            'display_order',
            'id',
            'name',
        ));
        if (Yii::app()->request->isPostRequest) {
            try {
                $admin->sortModel();
            } catch (Throwable $e) {
                Yii::log("Error sorting DNA Investigators: " . $e->getMessage(), CLogger::LEVEL_ERROR);
                // Silently ignore sort errors
            }
        } else {
            try {
                $admin->listModel();
            } catch (Throwable $e) {
                Yii::log("Error listing DNA Investigators: " . $e->getMessage(), CLogger::LEVEL_ERROR);
                // Render empty list view
                $this->render('//admin/generic/list', array(
                    'admin' => $admin,
                    'displayOrder' => 1,
                    'buttons' => true
                ));
            }
        }
    }
}
