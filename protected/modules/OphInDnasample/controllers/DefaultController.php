<?php

class DefaultController extends BaseEventTypeController
{

    public function volumeRemaining($event_id)
    {
        $volume_remaining = 0;
        if ($api = Yii::app()->moduleAPI->get('OphInDnaextraction')) {
            $volume_remaining = $api->volumeRemaining($event_id);
        }

        return $volume_remaining;
    }

    public function checkCreateAccess()
    {
        return $this->checkAccess('OprnEditDnaSample');
    }

    public function checkUpdateAccess()
    {
        return $this->checkAccess('OprnEditDnaSample');
    }

    public function checkViewAccess()
    {
        return $this->checkAccess('OprnEditDnaSample') || $this->checkAccess('OprnViewDnaSample');
    }

    public function checkPrintAccess()
    {
        return $this->checkAccess('OprnEditDnaSample') || $this->checkAccess('OprnViewDnaSample');
    }

    private function _registerDnaTestFormJs()
    {
        $assetPath = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.OphInDnaextraction.assets'), true);
        Yii::app()->clientScript->registerScriptFile($assetPath.'/js/dna_tests_view.js');
    }

    protected function initActionCreate()
    {
        // Check if patient_id is provided, if not redirect to home
        if (!isset($_REQUEST['patient_id'])) {
            $this->redirect('/');
            Yii::app()->end();
        }
        parent::initActionCreate();
    }

    public function actionCreate()
    {
        $this->_registerDnaTestFormJs();
        parent::actionCreate();
    }

    public function actionUpdate($id)
    {
        $this->_registerDnaTestFormJs();
        parent::actionUpdate($id);
        
        // Verify the event type is OphInDnasample
        if ($this->event && $this->event->eventType && $this->event->eventType->class_name !== 'OphInDnasample') {
            throw new CHttpException(400, 'Event type mismatch: Expected OphInDnasample, got ' . $this->event->eventType->class_name);
        }
    }

    public function actionView($id)
    {
        $this->_registerDnaTestFormJs();
        parent::actionView($id);
        
        // Verify the event type is OphInDnasample
        if ($this->event && $this->event->eventType && $this->event->eventType->class_name !== 'OphInDnasample') {
            throw new CHttpException(400, 'Event type mismatch: Expected OphInDnasample, got ' . $this->event->eventType->class_name);
        }
    }

    public function actionPrint($id = null)
    {
        // Try to get ID from request if not provided as parameter
        if ($id === null) {
            $id = Yii::app()->request->getParam('id');
        }
        
        // If still no ID, show a helpful message
        if ($id === null) {
            $this->layout = false;
            $this->renderText('<html><head><title>DNA Sample Print</title></head><body style="padding: 20px; font-family: Arial, sans-serif;"><h2>DNA Sample Print</h2><p>To print a DNA Sample event, please:</p><ol><li>Navigate to a patient record</li><li>Select a DNA Sample event</li><li>Use the Print button to print the event</li></ol></body></html>');
            return;
        }
        
        parent::actionPrint($id);
        
        // Verify the event type is OphInDnasample
        if ($this->event && $this->event->eventType && $this->event->eventType->class_name !== 'OphInDnasample') {
            throw new CHttpException(400, 'Event type mismatch: Expected OphInDnasample, got ' . $this->event->eventType->class_name);
        }
    }

    public function isRequiredInUI(BaseEventTypeElement $element)
    {
        return true;
    }
}
