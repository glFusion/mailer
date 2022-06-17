/**
 * Updates subscriber status and indicators.
 */
function MLR_toggleUserStatus(stat, id)
{
    var dataS = {
        'action': 'userstatus',
        'id': id,
        'newval': stat,
        'sid': Math.random(),
    };
    var data = $.param(dataS);
    $.ajax({
        type: "GET",
        dataType: "json",
        url: site_admin_url + "/plugins/mailer/ajax.php",
        data: data,
        success: function(result) {
            try {
                if (result.id != '') {
                    $('#userstatus'+id).html(result.html);
                }
            } catch(err) {
                console.log("Error updating user status");
                console.log(result);
            }
        },
        error: function() {
        }
    });
    return false;
}

