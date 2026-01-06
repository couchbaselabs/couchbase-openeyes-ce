<?php
/**
 * OpenEyes
 *
 * @link http://www.openeyes.org.uk
 * @copyright OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

$this->pageTitle = 'CatProm5 Events';
?>

<div class="oe-full-header use-full-screen">
    <div class="title wordcaps">
        <b>CatProm5 Events</b>
    </div>
</div>

<div class="oe-full-content use-full-screen">
    <?php
    // Get all events that have CatProm5EventResult elements
    $criteria = new CDbCriteria();
    $criteria->order = 'event_date DESC';
    $model = new CatProm5EventResult('search');
    $dataProvider = $model->search();
    
    if ($dataProvider->getTotalItemCount() > 0) {
        ?>
        <table class="standard">
            <thead>
                <tr>
                    <th>Event ID</th>
                    <th>Raw Score</th>
                    <th>Rasch Score</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($dataProvider->getData() as $event) {
                    $eventRecord = Event::model()->findByPk($event->event_id);
                    $patient = $eventRecord ? $eventRecord->episode->patient : null;
                    ?>
                    <tr>
                        <td><?php echo CHtml::encode($event->event_id); ?></td>
                        <td><?php echo CHtml::encode($event->total_raw_score); ?></td>
                        <td><?php echo CHtml::encode($event->total_rasch_measure); ?></td>
                        <td>
                            <?php if ($eventRecord) { ?>
                                <a href="<?php echo $this->createUrl('/patient/episode/' . $eventRecord->episode_id); ?>" class="button hint">View Event</a>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
    } else {
        ?>
        <div class="alert-box info">
            No CatProm5 events found. Create a new event to get started.
        </div>
        <?php
    }
    ?>
</div>
