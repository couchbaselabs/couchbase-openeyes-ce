<?php
$eventType = $event->eventType;
if ($eventType && $eventType->class_name) {
    $event_path = Yii::app()->createUrl($eventType->class_name . '/default/view') . '/';
    $assetModule = $eventType->class_name;
} else {
    $event_path = null;
    $assetModule = null;
}
?>
<a href="<?php echo $event_path ? $event_path . $event->id : '#' ?>" data-id="<?php echo $event->id ?>">
    <?php
    if ($assetModule && file_exists(Yii::getPathOfAlias('application.modules.' . $assetModule . '.assets'))) {
        $assetpath = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.' . $assetModule . '.assets'), true) . '/';
    } else {
        $assetpath = '/assets/';
    }
    ?>
    <img src="<?php echo $assetpath . 'img/small.png' ?>" alt="op" width="19" height="19" />
    <?php echo $text ?>
</a>
