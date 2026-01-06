<?php
/* @var $this TrialController */
/* @var $trial Trial */
/* @var $newPermission UserTrialAssignment */
/* @var $permissions TrialPermission[] */
?>

<?php $this->renderPartial('_trial_header', array(
    'trial' => $trial,
    'title' => CHtml::encode($trial->name),
)); ?>

<div class="oe-full-content subgrid oe-worklists">
  <main class="oe-full-main">
    <section class="element edit full cols-10">
      <div class="element-fields">
        <h3 class="element-title">Add Permission</h3>
        
        <form id="add-permission-form" method="post" class="oe-ehr-form">
          <input type="hidden" name="id" value="<?= $trial->id; ?>">
          
          <table class="standard">
            <colgroup>
              <col class="cols-2">
              <col class="cols-4">
            </colgroup>
            <tbody>
            <tr>
              <td>
                <?= CHtml::label('User', 'autocomplete_user_id'); ?>
              </td>
              <td>
                <?php
                $this->widget('application.widgets.AutoCompleteSearch',
                  [
                      'field_name' => "autocomplete_user_id",
                      'htmlOptions' =>
                          [
                              'placeholder' => 'Search Users',
                          ],
                      'hide_no_result_msg' => true
                  ]);
                ?>
              </td>
            </tr>
            <tr>
              <td>
                <?= CHtml::label('Role', 'user_role'); ?>
              </td>
              <td>
                <?= CHtml::textField(
                    'user_role',
                    '',
                    array('maxlength' => 255, 'placeholder' => 'e.g., Research Assistant, Study Lead')
                ); ?>
              </td>
            </tr>
            <tr>
              <td>
                <?= CHtml::label('Permission Level', 'permission'); ?>
              </td>
              <td>
                <?= CHtml::dropDownList(
                    'permission',
                    '',
                    CHtml::listData($permissions, 'id', 'name'),
                    array('id' => 'permission', 'prompt' => 'Select a permission level...')
                ); ?>
              </td>
            </tr>
            </tbody>
          </table>

          <div id="selected_user_wrapper" style="display: none; margin-top: 20px;">
            <button type="submit" class="secondary small js-save-permission">Share with &nbsp;
              <span id="user_name"></span>
            </button>
            &nbsp;
            <a href="javascript:void(0)" class="button event-action cancel small"
               onclick="removeSelectedUser()">Clear</a>
            <?= CHtml::hiddenField(
                'user_id',
                '',
                array('class' => 'hidden_id')
            ); ?>
          </div>

          <div class="alert-box info with-icon" id="no-user-result" style="display: none;">
            Can't find the user you're looking for? They might not have the permission to view trials.
            <br/>
            Please contact an administrator and ask them to give that user the "Create Trial" or "Trial User" role.
          </div>

          <div style="margin-top: 20px;">
            <a href="<?= $this->createUrl('permissions', array('id' => $trial->id)); ?>" class="button event-action cancel">Cancel</a>
          </div>
        </form>
      </div>
    </section>
  </main>
</div>

<script type="text/javascript">
  function addItem(wrapper_id, response) {
    var $wrapper = $('#' + wrapper_id);

    $('#user_name').text(response.label);
    $wrapper.show();
    $wrapper.find('.hidden_id').val(response.id);
  }

  function removeSelectedUser() {
    $('#no_user_result').hide();
    $('#user_name').text('');
    $('#selected_user_wrapper').hide();
    $('#autocomplete_user_id').val('');
  }

  $(document).ready(function () {
    $('#selected_user_wrapper').on('click', '.remove', function () {
      removeSelectedUser();
    });

    $('#add-permission-form').on('submit', function () {
      var user_id = $('[name="user_id"]').val();
      if (user_id == '') {
        new OpenEyes.UI.Dialog.Alert({
          content: "Please select a user and choose a permission level"
        }).open();
        return false;
      }
      
      var permission = $('#permission').val();
      if (permission == '') {
        new OpenEyes.UI.Dialog.Alert({
          content: "Please select a permission level"
        }).open();
        return false;
      }

      return true;
    });
  });

  $(document).ready(function() {
    OpenEyes.UI.AutoCompleteSearch.init({
      input: $('#autocomplete_user_id'),
      url: '<?= $this->createUrl('userAutoComplete') ?>',
      params: {
        'id': function () {return "<?= $trial->id ?>"},
      },
      maxHeight: '200px',
      minimumCharacterLength: 1,
      onSelect: function () {
        let response = OpenEyes.UI.AutoCompleteSearch.getResponse();
        let input = OpenEyes.UI.AutoCompleteSearch.getInput();

        removeSelectedUser();
        addItem('selected_user_wrapper', response);
        $('#autocomplete_user_id').val($('#user_name').text());
      }
    });
  });
</script>
