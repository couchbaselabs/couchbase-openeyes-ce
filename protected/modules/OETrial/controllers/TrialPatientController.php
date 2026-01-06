<?php

/**
 * Class TrialPatientController
 */
class TrialPatientController extends BaseModuleController
{
    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl',
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array(
                'allow',
                'actions' => array('changeStatus', 'updateExternalId', 'updateTreatmentType','updateComment'),
                'expression' => function ($user) {
                    // Allow authenticated users to attempt the action
                    // The action will validate the ID and permissions
                    return $user->checkAccess("TaskViewTrial");
                },
            ),
            array(
                'deny',  // deny all users
                'users' => array('*'),
            ),
        );
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param int $id the ID of the model to be loaded
     * @return TrialPatient the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        /* @var TrialPatient $model */
        $model = TrialPatient::model()->findByPk($id);
        if ($model === null) {
            throw new CHttpException(404, 'The requested page does not exist.');
        }

        return $model;
    }

    /**
     * Performs the AJAX validation.
     * @param TrialPatient $model the model to be validated
     */
    protected function performAjaxValidation($model)
    {
        if (isset($_POST['ajax']) && $_POST['ajax'] === 'trial-patient-form') {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }

    /**
     * Changes the status of a patient in a trial to a given value
     * @throws Exception Thrown the model cannot be saved
     */
    public function actionChangeStatus()
    {
        $id = Yii::app()->getRequest()->getParam('id');
        if (!$id) {
            throw new CHttpException(400, 'Missing required parameter: id');
        }
        
        $newStatusCode = Yii::app()->getRequest()->getParam('new_status');
        if (!$newStatusCode) {
            throw new CHttpException(400, 'Missing required parameter: new_status');
        }
        
        $trialPatient = $this->loadModel($id);
        
        // Check that the trial exists
        if (!$trialPatient->trial) {
            throw new CHttpException(404, 'Trial not found.');
        }
        
        // Check edit permission on the trial
        $trialPermission = $trialPatient->trial->getUserPermission(Yii::app()->user->id);
        if (!($trialPermission && $trialPermission->can_edit)) {
            throw new CHttpException(403, 'You do not have permission to edit this trial patient.');
        }
        
        $new_status = TrialPatientStatus::model()->find('code = ?', array($newStatusCode));
        $trialPatient->changeStatus($new_status);
    }

    /**
     * Changes the external_trial_identifier of a TrialPatient record
     *
     * @throws Exception Thrown if an error occurs when saving the model or if it cannot be found
     */
    public function actionUpdateExternalId()
    {
        $id = Yii::app()->request->getParam('id');
        if (!$id) {
            throw new CHttpException(400, 'Missing required parameter: id');
        }
        
        if (!isset($_POST['new_external_id'])) {
            throw new CHttpException(400, 'Missing required parameter: new_external_id');
        }
        
        $model = $this->loadModel($id);
        
        // Check that the trial exists
        if (!$model->trial) {
            throw new CHttpException(404, 'Trial not found.');
        }
        
        // Check edit permission on the trial
        $trialPermission = $model->trial->getUserPermission(Yii::app()->user->id);
        if (!($trialPermission && $trialPermission->can_edit)) {
            throw new CHttpException(403, 'You do not have permission to edit this trial patient.');
        }
        
        $model->updateExternalId($_POST['new_external_id']);
    }
    
    /**
     * Changes the comment of a TrialPatient record
     *
     * @throws Exception Thrown if an error occurs when saving the model or if it cannot be found
     */
    public function actionUpdateComment()
    {
        $id = Yii::app()->request->getParam('id');
        if (!$id) {
            throw new CHttpException(400, 'Missing required parameter: id');
        }
        
        if (!isset($_POST['new_comment'])) {
            throw new CHttpException(400, 'Missing required parameter: new_comment');
        }
        
        $model = $this->loadModel($id);
        
        // Check that the trial exists
        if (!$model->trial) {
            throw new CHttpException(404, 'Trial not found.');
        }
        
        // Check edit permission on the trial
        $trialPermission = $model->trial->getUserPermission(Yii::app()->user->id);
        if (!($trialPermission && $trialPermission->can_edit)) {
            throw new CHttpException(403, 'You do not have permission to edit this trial patient.');
        }
        
        $model->updateComment($_POST['new_comment']);
    }

    /**
     * Updates the treatment type of a trial-patient with a new treatment type
     *
     * @throws Exception Thrown if an error occurs when saving the TrialPatient
     */
    public function actionUpdateTreatmentType()
    {
        $id = Yii::app()->request->getParam('id');
        if (!$id) {
            throw new CHttpException(400, 'Missing required parameter: id');
        }
        
        if (!isset($_POST['treatment_type'])) {
            throw new CHttpException(400, 'Missing required parameter: treatment_type');
        }
        
        $model = $this->loadModel($id);
        
        // Check that the trial exists
        if (!$model->trial) {
            throw new CHttpException(404, 'Trial not found.');
        }
        
        // Check edit permission on the trial
        $trialPermission = $model->trial->getUserPermission(Yii::app()->user->id);
        if (!($trialPermission && $trialPermission->can_edit)) {
            throw new CHttpException(403, 'You do not have permission to edit this trial patient.');
        }
        
        $treatmentType = TreatmentType::model()->findByPk($_POST['treatment_type']);
        $model->updateTreatmentType($treatmentType);
    }
}
