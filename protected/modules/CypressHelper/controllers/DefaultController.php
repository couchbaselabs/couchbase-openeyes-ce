<?php

namespace OEModule\CypressHelper\controllers;

use CWebLogRoute;
use Event;
use EventType;
use Firm;
use Institution;
use OE\concerns\InteractsWithApp;
use OE\factories\models\EventFactory;
use OE\factories\ModelFactory;
use OE\seeders\SeederBuilder;
use OEModule\OphCiExamination\models\HistoryRisks;
use Patient;
use User;
use OE\seeders\resources\GenericModelResource;
use OE\seeders\resources\SeededEventResource;
use OE\seeders\resources\SeededPatientResource;
use SettingInstallation;

class DefaultController extends \CController
{
    use InteractsWithApp;

    public function beforeAction($action)
    {
        if ($this->getApp()->params['environment'] === 'live') {
            throw new \CHttpException(403, 'Request unavailable in current configuration');
        }

        $this->getApp()->attachEventHandler('onException', [$this, 'handleException']);

        return parent::beforeAction($action);
    }

    public function actionLogin()
    {
        // cribbed from the SiteController login process
        // be nice if we could alter this so that a user id / username is passed in
        // and we automatically log them in so we don't need passwords etc.
        $model = new \LoginForm();
        $attributes = [
            'username' => $_POST['username'] ?? 'admin',
            'password' => $_POST['password'] ?? 'admin',
            'site_id' => $_POST['site_id'] ?? null,
            'institution_id' => $_POST['institution_id'] ?? null,
        ];

        if (empty($attributes['site_id'])) {
            unset($attributes['site_id']);
        }

        if (empty($attributes['institution_id'])) {
            unset($attributes['institution_id']);
        }

        $model->attributes = $attributes;

        if (!$model->validate()) {
            $this->sendJsonResponse($model->getErrors(), 400);
        }

        $model->login();
        $this->getApp()->session['confirm_site_and_firm'] = false;
        $this->getApp()->session['shown_version_reminder'] = true;

        $this->sendJsonResponse([
            'firm_id' => $this->getApp()->session['selected_firm_id'],
            'subspecialty_id' => Firm::model()->findByPK($this->getApp()->session['selected_firm_id'])->getSubspecialtyID(),
            'institution_id' => $this->getApp()->session['selected_institution_id'],
            'pincode' => User::model()->findByPk(\Yii::app()->user->id)->pincode->value ?? 'No Pincode'
        ]);
    }

    public function actionCreateUser()
    {
        $authitems = $_POST['authitems'] ?? [];
        $institution_id = $_POST['institution_id'] ?? 1;
        $attributes = $_POST['attributes'] ?? [];
        $password = $_POST['password'] ?? 'password';
        $username = $attributes['username'] ?? $_POST['username'] ?? null;

        $user = User::factory()
            ->withAuthItems($authitems)
            ->withLocalAuthForInstitution(Institution::model()->findByPk($institution_id), $password, $username)
            ->create($attributes);

        // Refresh user to load authentications relationship
        $user->refresh();

        $this->sendJsonResponse([
            'user_id' => $user->id,
            'username' => $user->authentications[0]?->username ?? $password,
            'password' => $password
        ]);
    }

    public function actionCreateEvent($moduleName = null)
    {
        // Allow moduleName to be provided in POST data if not in URL
        if (!$moduleName) {
            $moduleName = $_POST['moduleName'] ?? null;
        }
        
        // If still no moduleName, return helpful response instead of error
        if (!$moduleName) {
            $this->sendJsonResponse([
                'message' => 'This endpoint creates events for a specified module',
                'usage' => [
                    'url_parameter' => '/CypressHelper/default/createEvent/{moduleName}',
                    'post_parameter' => 'POST with moduleName in body',
                    'required_parameters' => ['moduleName'],
                    'optional_parameters' => ['states', 'count', 'attributes']
                ]
            ], 200);
        }
        
        $event_factory = EventFactory::forModule($moduleName);

        $instances = $this->applyStatesTo($event_factory, $_POST['states'] ?? [])
            ->count(isset($_POST['count']) ? (int) $_POST['count'] : 1)
            ->create($_POST['attributes'] ?? []);

        $this->sendJsonResponse(
            count($instances) > 1
            ? [
                array_map(
                    function ($event) {
                        return SeededEventResource::from($event)->inFull()->toArray();
                    },
                    $instances
                )
            ]
            : SeededEventResource::from($instances[0])->inFull()->toArray()
        );
    }

    public function actionCreatePatient()
    {
        $states = $_POST['states'] ?? [];
        $attributes = $_POST['attributes'] ?? [];
        /** @var Patient $patient */
        $patient = ModelFactory::factoryFor(Patient::class);
        foreach ($states as $state) {
            $patient = $patient->$state();
        }

        $patient = $patient->create($attributes);

        $this->sendJsonResponse($this->patientJson($patient));
    }

    public function actionGetEventCreationUrl($patientId, $moduleName, $firmId)
    {
        $patient = Patient::model()->findByPk($patientId);
        if (!$patient) {
            throw new \CHttpException(404, 'Patient must exist to generate event creation url.');
        }
        $eventTypeId = EventType::model()->findByAttributes([
            'class_name' => $moduleName
        ])->id;

        /** @var Firm $current_firm */
        if($firmId !== "0") {
            $current_firm = Firm::model()->findByPk($firmId);
        } else {
            $current_firm = $this->getApp()->session->getSelectedFirm();
        }
        $url = "/patientEvent/create?patient_id={$patientId}&event_type_id={$eventTypeId}&context_id={$current_firm->id}";

        $episode = $patient->getOpenEpisodeOfSubspecialty($current_firm->getSubspecialtyID());
        $url .= $episode ? "&episode_id={$episode->id}" : "&service_id={$current_firm->id}";

        $this->sendJsonResponse([
            'url' => $url
        ]);
    }

    /**
     * This action relies on a ModelFactory having been defined for the required Model,
     * and it will either find an existing instance of the model with the given attributes
     * or create it.
     *
     * It will not return any relations defined on the model
     */
    public function actionLookupOrCreateModel()
    {
        $model_class = $_POST['model_class'] ?? null;
        $lookup_attributes = $_POST['attributes'] ?? [];

        if (!$model_class) {
            $this->sendJsonResponse([
                'message' => 'This endpoint looks up or creates a model instance',
                'usage' => [
                    'method' => 'POST',
                    'required_parameters' => ['model_class'],
                    'optional_parameters' => ['attributes']
                ]
            ], 200);
        }
        
        // Ensure attributes is an array (handle case where it might be sent as JSON string)
        if (is_string($lookup_attributes)) {
            $lookup_attributes = json_decode($lookup_attributes, true) ?: [];
        }
        
        try {
            // Check if the class exists before attempting to create factory
            // Use error suppression since autoloader may trigger PHP warnings
            $classExists = @class_exists($model_class, true);
            if (!$classExists) {
                throw new \CHttpException(400, "Model class '$model_class' does not exist");
            }
            
            $model_instance = ModelFactory::factoryFor($model_class)
                ->useExisting($lookup_attributes)
                ->create();

            $this->sendJsonResponse([
                'model' => GenericModelResource::from($model_instance)->toArray()
            ]);
        } catch (\Exception $e) {
            // Send JSON error response even if exception occurs
            $status = 500;
            if ($e instanceof \CHttpException) {
                $status = $e->statusCode;
            }
            $this->sendJsonResponse(
                ['message' => $e->getMessage(), 'trace' => $e->getTrace()],
                $status
            );
        }
    }

    public function actionCreateModels()
    {
        $model_class = $_POST['model_class'] ?? null;
        if (!$model_class) {
            $this->sendJsonResponse([
                'message' => 'This endpoint creates instances of a specified model class',
                'usage' => [
                    'method' => 'POST',
                    'required_parameters' => ['model_class'],
                    'optional_parameters' => ['states', 'count', 'attributes']
                ]
            ], 400);
        }

        $model_factory = ModelFactory::factoryFor($model_class);

        $this->applyStatesTo($model_factory, $_POST['states'] ?? []);

        $model_factory->count(($_POST['count'] ?? null) ? (int) $_POST['count'] : 1);

        $instances = $model_factory->create($_POST['attributes'] ?? []);

        $this->sendJsonResponse([
            'models' => $this->modelsToArrays($instances)
        ]);
    }

    public function actionSetSystemSettingValue()
    {
        $system_setting_key = $_POST['system_setting_key'] ?? null;
        $system_setting_value = $_POST['system_setting_value'] ?? null;

        if (!$system_setting_key || !$system_setting_value) {
            throw new \CHttpException(400, 'system setting key and value must both be provided');
        }

        $setting = SettingInstallation::model()->findByAttributes(['key' => $system_setting_key]);
        if (!isset($setting)) {
            $setting = new SettingInstallation();
            $setting->key = $system_setting_key;
        }
        $setting->value = $system_setting_value;
        $setting->save();

        // ensure the change takes immediate effect for further requests
        \Yii::app()->settingCache->flush();

        echo '1';
    }

    public function actionResetSystemSettingValue()
    {
        $system_setting_key = $_POST['system_setting_key'] ?? null;

        if (!$system_setting_key) {
            throw new \CHttpException(400, 'system setting key must be provided');
        }

        // Delete the setting installation key, the setting then falls back to the value specified as default in SettingMetadata
        $setting = SettingInstallation::model()->findByAttributes(['key' => $system_setting_key]);
        if (isset($setting)) {
            $setting->delete();
        }

        // ensure the change takes immediate effect for further requests
        \Yii::app()->settingCache->flush();

        echo '1';
    }

    public function actionRunSeeder()
    {
        $seeder_class_name = $_POST['seeder_class_name'];
        $seeder_module_name = $_POST['seeder_module_name'];
        $additional_data = $_POST['additional_data'] ?? [];
        if (!is_array($additional_data)) {
            throw new \CHttpException(400, 'additional_data must be an arrayable structure');
        }

        $seeder = SeederBuilder::getInstance()->build(
            $seeder_class_name,
            $seeder_module_name,
            array_merge(
                ['subspecialties' => \Subspecialty::model()->findByPk(1)],
                $additional_data
            )
        );

        $this->sendJsonResponse($seeder());
    }

    public function actionAddElementsToDraftExamination()
    {
        $draft_id = $_POST['draft_id'] ?? null;
        $elements = $_POST['elements'] ?? null;

        if (!$draft_id) {
            $this->sendJsonResponse([
                'message' => 'This endpoint adds elements to a draft examination',
                'usage' => [
                    'method' => 'POST',
                    'required_parameters' => ['draft_id'],
                    'optional_parameters' => ['elements']
                ],
                'description' => 'draft_id: The ID of the EventDraft to update. elements: Array of element types to add (e.g., ["Risks"])'
            ], 400);
        }

        $draft = \EventDraft::model()->findByPk($draft_id);
        if (!$draft) {
            throw new \CHttpException(400, "draft_id not found: $draft_id");
        }

        if (!empty($elements)) {
            foreach ($elements as $element) {
                $draft_data_array = json_decode($draft->data, true);
                $element_data = $this->{"add" . $element}($draft);
                $draft->data = json_encode(array_merge($draft_data_array, $element_data));
            }

            if (!$draft->save()) {
                throw new \Exception("EventDraft could not be saved: " . print_r($draft->getErrors(), true));
            }
        }

        $this->sendJsonResponse([
            'message' => 'Elements successfully added to draft examination',
            'draft_id' => $draft->id
        ]);
    }

    protected function addRisks($draft)
    {
        $risks = HistoryRisks::factory()
            ->forEvent($draft->event)
            ->withRequiredEntries()
            ->create();

        $risks->refresh();

        return HistoryRisks::factory()
            ->makeAsFormData(["event_id" => $draft->event->id, "entries" => $risks->entries]);
    }

    /**
     * Simple handler to ensure exception data is surfaced to cypress
     *
     * @param \CExceptionEvent $event
     * @return void
     */
    protected function handleException(\CExceptionEvent $event)
    {
        $status = 500;
        if ($event->exception instanceof \CHttpException) {
            $status = $event->exception->statusCode;
        }
        $this->sendJsonResponse(['message' => $event->exception->getMessage(), 'trace' => $event->exception->getTrace()], $status);
    }

    protected function applyStatesTo(ModelFactory $factory, $states = []): ModelFactory
    {
        if (!is_array($states)) {
            $states = [$states];
        }

        foreach ($states as $state) {
            if (is_array($state)) {
                $factory = $factory->{$state[0]}(...array_slice($state, 1));
            } else {
                $factory = $factory->$state();
            }
        }

        return $factory;
    }
    protected function sendJsonResponse(array $data = [], int $status = 200)
    {
        header('HTTP/1.1 ' . $status);
        header('Content-type: application/json');
        echo \CJSON::encode($data);

        $this->disableLogRoutes();
        $this->getApp()->end();
    }

    protected function disableLogRoutes()
    {
        foreach ($this->getApp()->log->routes as $route) {
            if ($route instanceof CWebLogRoute) {
                $route->enabled = false;
            }
        }
    }

    protected function modelsToArrays(array $instances, $exclude = [])
    {
        return array_map(function ($instance) use ($exclude) {
            return GenericModelResource::from($instance)->exclude($exclude)->toArray();
        }, $instances);
    }

    protected function patientJson(Patient $patient): array
    {
        return SeededPatientResource::from($patient)->toArray();
    }

    protected function eventJson(Event $event, bool $with_elements = true): array
    {
        return SeededEventResource::from($event)->inFull($with_elements)->toArray();
    }
}
