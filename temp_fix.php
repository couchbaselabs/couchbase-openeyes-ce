<?php
$file = 'protected/modules/OphDrPGDPSD/controllers/PSDController.php';
$content = file_get_contents($file);

$old = <<<'OLDCODE'
    public function actionUnlockPSD()
    {
        $step_id = \Yii::app()->request->getParam('step_id', null);
        $step_type_id = \Yii::app()->request->getParam('step_type_id', null);
        $visit_id = \Yii::app()->request->getParam('visit_id', null);

        $wl_patient = WorklistPatient::model()->findByPk($visit_id);

        if ($wl_patient && !$wl_patient->pathway->start_time) {
            $wl_patient->pathway->start_time = date('Y-m-d H:i:s');
            $wl_patient->pathway->save();
        }

        $this->actionGetPathStep(0, $step_id, $visit_id, $step_type_id, 1);
    }
OLDCODE;

$new = <<<'NEWCODE'
    public function actionUnlockPSD()
    {
        $step_id = \Yii::app()->request->getParam('step_id', null);
        $step_type_id = \Yii::app()->request->getParam('step_type_id', null);
        $visit_id = \Yii::app()->request->getParam('visit_id', null);

        if (!$step_id && !$step_type_id) {
            throw new CHttpException(400, 'step_id or step_type_id parameter is required.');
        }

        $wl_patient = WorklistPatient::model()->findByPk($visit_id);

        if ($wl_patient && !$wl_patient->pathway->start_time) {
            $wl_patient->pathway->start_time = date('Y-m-d H:i:s');
            $wl_patient->pathway->save();
        }

        $this->actionGetPathStep(0, $step_id, $visit_id, $step_type_id, 1);
    }
NEWCODE;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed";
?>
