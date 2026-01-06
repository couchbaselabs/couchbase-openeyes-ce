/**
 * Created by veta on 08/04/15.
 */

$(document).ready(function () {
    $('#site_id').change(function () {
        this.form.submit();
    });
    $('#subspecialty_id').change(function () {
        this.form.submit();
    });
});

function DeleteCommonDrug(ssdId) {
    if (ssdId === undefined) {
        return false;
    } else {

        $.ajax({
                url: "/OphDrPrescription/admin/default/commondrugsdelete?itemId=" + ssdId,
                error: function () {
                    console.log("ERROR, something went wrong!");
                },
                success: function () {
                    // we can dynamicaly rebuild the list here, but I think we don't need to develop more code for that :)
                    window.location.reload();
                }
            }
        );
    }
}

function addItem(drugId) {
    $.ajax({
            url: "/OphDrPrescription/admin/default/commondrugsadd?drug_id=" + drugId + "&site_id=" + $('#site_id').val() + "&subspecialty_id=" + $('#subspecialty_id').val(),
            error: function () {
                console.log("ERROR, something went wrong!");
            },
            success: function () {
                // we can dynamicaly rebuild the list here, but I think we don't need to develop more code for that :)
                window.location.reload();
            }
        }
    );

}