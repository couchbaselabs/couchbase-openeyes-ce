<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
?>
<form id="dnaextraction_addNewStorageForm">
    <table class="standard row printLabelPanel">
        <tbody>
        <tr>
            <td class="data-group">
                <h3 class="cols-3 column">Box:</h3>
                <div class="cols-9 column end">
                    <?=\CHtml::dropDownList('dnaextraction_box_id', $element->box_id, CHtml::listData(OphInDnaextraction_DnaExtraction_Box::model()->findAll(array('order' => 'display_order asc')), 'id', 'value'), array('empty' => '- Select -', 'onchange' => 'getAvailableLetterNumberToBox( this )'))?>
                </div>
            </td>
            <td class="data-group">
                <h3 class="cols-3 column">Letter:</h3>
                <div class="cols-9 column end">
                    <?=\CHtml::textField('dnaextraction_letter', $element->letter, array('onkeyup' => "setUppercase( this )"))?>
                </div>
            </td>
            <td class="data-group">
                <h3 class="cols-3 column">Number:</h3>
                <div class="cols-9 column end">
                    <?=\CHtml::textField('dnaextraction_number', $element->number)?>
                </div>
            </td>
        </tr>
        <tr>
            <td class="data-group">
                <?= CHtml::button('Save', [
                    'class' => 'button small secondary',
                    'name' => 'save',
                    'id' => 'save-new-storage-btn'
                ]);
?>
            </td>
        </tr>
</form>

<script>
// Define baseUrl if not already defined
if (typeof baseUrl === 'undefined') {
    baseUrl = '/';
}

// Define YII_CSRF_TOKEN if not already defined
if (typeof YII_CSRF_TOKEN === 'undefined') {
    YII_CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Define jQuery if not already loaded
if (typeof $ === 'undefined') {
    console.error('jQuery is not loaded');
}

// Define OpenEyes if not already loaded
if (typeof OpenEyes === 'undefined') {
    OpenEyes = { UI: { Dialog: {} } };
}

// Define JavaScript functions used in the form
function getAvailableLetterNumberToBox( obj ){
    obj = $(obj);
    
    $.ajax({
        'type': 'POST',
        'url': baseUrl+'/OphInDnaextraction/default/getAvailableLetterNumberToBox',
        'data': {
                box_id: obj.val(),
                YII_CSRF_TOKEN: YII_CSRF_TOKEN
        },  
        'dataType': 'json',
        'success': function(response) {
            if (typeof(response.letter) != "undefined"){
                $('#dnaextraction_letter').attr("placeholder", response.letter);
                $('#dnaextraction_number').attr("placeholder", response.number);

                $('#dnaextraction_letter').prop('disabled', false);
                $('#dnaextraction_number').prop('disabled', false);
            } else {        
                $('#dnaextraction_letter').prop('disabled', true);
                $('#dnaextraction_number').prop('disabled', true);
            }
        }
    });
}

function setUppercase( obj ){
    obj.value = obj.value.toUpperCase();
}

function saveNewStorage(){
    data = $('#dnaextraction_addNewStorageForm').serialize() + '&YII_CSRF_TOKEN=' + YII_CSRF_TOKEN;
    
    var result = false;
    $.ajax({
        'type': 'POST',
        'url': baseUrl+'/OphInDnaextraction/default/saveNewStorage',
        'data': data,
        'dataType': 'json',
        'async': false,
        'success': function(response) {
            if(response.s == '0'){
                new OpenEyes.UI.Dialog.Alert({
                    content: response.msg
                }).open();
            } else {
                
                refreshStorageSelect( response.selected );
                result = true;
            }
        }
    });
    
    return result;
}

function refreshStorageSelect( selectedID ){
    $.ajax({
        'type': 'GET',
        'url': baseUrl+'/OphInDnaextraction/default/refreshStorageSelect',    
        'dataType': 'json',
        'success': function(response) {
            
            var count = Object.keys(response).length;
            var option = '<option value="">- Select -</option>';
            
            for(var i = 0; i < count; i++){
                key = Object.keys(response)[i];
                value = Object.values(response)[i];

                if(key == selectedID){
                    option += '<option value="'+key+'" SELECTED>'+value+'</option>';
                } else {
                    option += '<option value="'+key+'">'+value+'</option>';
                }

            }
            
            $('#Element_OphInDnaextraction_DnaExtraction_storage_id').html(option);
           
        }
    });
}
</script>

