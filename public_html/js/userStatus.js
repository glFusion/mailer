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
                    id = result.id;
                    icon1 = '<i class="uk-icon ' + result.icon1_cls + '" ';
                    if (result.newstat != 1) {
                        icon1 = icon1 +
                            "onclick='MLR_toggleUserStatus(\"1\", \"" + id +
                            "\")';";
                    }
                    icon1 = icon1 + '/></i>';

                    icon2 = '<i class="uk-icon ' + result.icon2_cls + '" ';
                    if (result.newstat != 0) {
                        icon2 = icon2 +
                            "onclick='MLR_toggleUserStatus(\"0\", \"" + id +
                            "\")';";
                    }
                    icon2 = icon2 + '/></i>';

                    icon3 = '<i class="uk-icon ' + result.icon3_cls + '" ';
                    if (result.newstat != 2) {
                        icon3 = icon3 +
                            "onclick='MLR_toggleUserStatus(\"0\", \"" + id +
                            "\")';";
                    }
                    icon3 = icon3 + '/></i>';
                    $('#userstatus'+id).html(
                        icon1 + '&nbsp;' +icon2 + '&nbsp;' +icon3
                    );
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

