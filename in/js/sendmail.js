$(document).ready(function() {
    var clipboard = new ClipboardJS('.copyText');

    clipboard.on('success', function(e) {
        alert(e.text + lang.copy_successfully);

        e.clearSelection();
    });

    clipboard.on('error', function(e) {
        alert(lang.copy_failed);
    });

    if (CKEDITOR.env.ie && CKEDITOR.env.version < 9)
        CKEDITOR.tools.enableHtml5Elements(document);

    // The trick to keep the editor in the sample quite small
    // unless user specified own height.
    CKEDITOR.config.height = 150;
    CKEDITOR.config.width = 'auto';

    var texteditor = (function() {
        var wysiwygareaAvailable = isWysiwygareaAvailable(),
            isBBCodeBuiltIn = !!CKEDITOR.plugins.get('bbcode');

        return function() {
            var editorElement = CKEDITOR.document.getById('editor');

            // Depending on the wysiwygare plugin availability initialize classic or inline editor.
            if (wysiwygareaAvailable) {
                CKEDITOR.replace('editor');
            } else {
                editorElement.setAttribute('contenteditable', 'true');
                CKEDITOR.inline('editor');

                // TODO we can consider displaying some info box that
                // without wysiwygarea the classic editor may not work.
            }
        };

        function isWysiwygareaAvailable() {
            // If in development mode, then the wysiwygarea must be available.
            // Split REV into two strings so builder does not replace it :D.
            if (CKEDITOR.revision == ('%RE' + 'V%')) {
                return true;
            }

            return !!CKEDITOR.plugins.get('wysiwygarea');
        }
    })();

    // 啟動
    texteditor();

    $('#previewMailBtn').click(function() {
        $('#previewMail').empty();

        if ($('.recipientCheck:checked').val() === 'uploadCsv') {
            var csrftoken = csrf;
            var data = {
                'subject': $('#subject').val(),
                'message': CKEDITOR.instances.editor.getData()
            };

            $.ajax({
                type: "POST",
                url: "sendmail_action.php?a=preview",
                data: {
                    csrftoken: csrftoken,
                    data: data
                },
            }).done(function(resp) {
                // $('#preview_result').html(resp);
                var res = JSON.parse(resp);
                $('#previewMail').append(
                    res.result.subject +
                    `<br><br>` +
                    `<div class="alert alert-secondary" role="alert">
                    ` + res.result.message + `
                    </div>`
                );

                $('#previewMailModal').modal('show');
            }).fail(function(jqXHR, textStatus) {
                alert('Request failed: ' + textStatus);
            });
        } else {
            $('#previewMail').append(
                $('#subject').val() +
                `<br><br>` +
                `<div class="alert alert-secondary" role="alert">
            ` + CKEDITOR.instances.editor.getData() + `
          </div>`
            );
            $('#previewMailModal').modal('show');
        }
    });
});

$(document).on('click', '#memberCheckbox', function() {
    $('#sendData').empty();
    $('#sendMemberCount').html(lang.Total + ` 0 ` + lang.people);
    $('#sendData').append(`<input type="text" class="form-control" id="sendTo" placeholder="` + lang.can_be_multiple_entered_separated_by_half_width_commas + `">`);
    $('#csvfile').val('');
});

$(document).on('click', '#allMemberCheckbox', function() {
    var csrftoken = csrf;
    $('#sendData').empty();
    $('#csvfile').val('');
    $('#sendTo').val('');

    $.ajax({
        type: "POST",
        url: "sendmail_action.php?a=allMemberCount",
        data: {
            csrftoken: csrftoken
        },
    }).done(function(resp) {
        // $('#preview_result').html(resp);
        var res = JSON.parse(resp);

        $('#sendMemberCount').empty();
        if (res.status == 'success') {
            $('#sendMemberCount').html(lang.Total + ` ` + res.result + ` ` + lang.people);
        } else {
            $('#sendMemberCount').html(res.result);
        }
    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus);
    });
});

$(document).on('click', '#uploadCsvCheckbox', function() {
    $('#sendMemberCount').html(lang.Total + ` 0 ` + lang.people);
    $('#csv-upload-progress').html('');
    $('#sendData').empty();
    $('#sendTo').val('');
    $('#csvfile').val('');

    $('#uploadCsvModal').modal('show');
});

$(document).on('click', '#uploadCsv', function() {
    var formData = new FormData();
    formData.append('csv', $('input[name=csv]')[0].files[0]);

    $.ajax({
        type: "POST",
        url: "sendmail_action.php?a=uploadCsv",
        data: formData,
        xhr: function() {
            var myXhr = $.ajaxSettings.xhr();
            if (myXhr.upload) {
                myXhr.upload.addEventListener('progress', progressUpdate, false);
            }
            return myXhr;
        },
        cache: false,
        contentType: false,
        processData: false,
    }).done(function(resp) {
        // $('#preview_result').html(resp);
        var res = JSON.parse(resp);
        if (res.status == 'success') {
            addCsvTableColHtml(res.colName);
            $('#sendMemberCount').html(lang.Total + ` ` + res.count + ` ` + lang.people);
            $('#csv-upload-progress').html(`<div class="alert alert-success text-center">` + res.result + `</div>`);
        } else {
            $('#csv-upload-progress').html(`<div class="alert alert-danger text-center">` + res.result + `</div>`);
        }
    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus + ' ' + jqXHR.status);
    });
});

$(document).on('click', '#sendMailBtn', function() {
    var csrftoken = csrf;
    var data = {
        'recipient': $('.recipientCheck:checked').val(),
        'subject': $('#subject').val(),
        'message': CKEDITOR.instances.editor.getData()
    };

    if (data.recipient == 'member') {
        data.sendTo = $('#sendTo').val();
    }

    if (confirm(lang.are_you_sure_to_send_the_mail)) {
        $.blockUI({ message: "<img src=\"ui/loading.gif\" />" + lang.sending + "..." });
        $.ajax({
            type: "POST",
            url: "sendmail_action.php?a=sendMail",
            data: {
                csrftoken: csrftoken,
                data: data
            },
        }).done(function(resp) {
            $.unblockUI();
            // $('#preview_result').html(resp);
            var res = JSON.parse(resp);
            if (res.status == 'success') {
                alert(res.result);
                document.location.replace('./mail.php');
            } else {
                alert(res.result);
            }
        }).fail(function(jqXHR, textStatus) {
            $.unblockUI();
            alert('Request failed: ' + textStatus);
        });
    }
});

function addCsvTableColHtml(data) {
    $('#sendData').empty();

    var tdHtml = Object.values(data).reduce(function(accumulator, currentValue, currentIndex, array) {
        var str = currentValue.replace("\"", "");
        var str = str.replace("\"", "");
        return accumulator + `<td>` + str + `</td>`;
    }, '');

    $('#sendData').append(`
    <table class="table table-bordered">
      <thead class="thead-light">
        <tr>
          <th scope="col" class="copyText" data-clipboard-text="%S00">%S00</th>
          <th scope="col" class="copyText" data-clipboard-text="%S01">%S01</th>
          <th scope="col" class="copyText" data-clipboard-text="%S02">%S02</th>
          <th scope="col" class="copyText" data-clipboard-text="%S03">%S03</th>
          <th scope="col" class="copyText" data-clipboard-text="%S04">%S04</th>
          <th scope="col" class="copyText" data-clipboard-text="%S05">%S05</th>
          <th scope="col" class="copyText" data-clipboard-text="%S06">%S06</th>
          <th scope="col" class="copyText" data-clipboard-text="%S07">%S07</th>
          <th scope="col" class="copyText" data-clipboard-text="%S08">%S08</th>
          <th scope="col" class="copyText" data-clipboard-text="%S09">%S09</th>
          <th scope="col" class="copyText" data-clipboard-text="%S10">%S10</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          ` + tdHtml + `
        </tr>
      </tbody>
    </table>
    `);
}

function progressUpdate(e) {
    if (e.lengthComputable) {
        var max = e.total;
        var current = e.loaded;
        var Percentage = Math.round((current * 100) / max);

        if (Percentage >= 100) {
            // process completed
            $('#csv-upload-progress').html(csvProccessingTmpl(100));
            return;
        }
        $('#csv-upload-progress').html(progressTmpl(Percentage));
    }
}

function csvProccessingTmpl() {
    return `
    <h5 align="center">
        ` + lang.importing + `...<img width="30px" height="30px" src="ui/loading.gif" />
    </h5>
    `
}

function progressTmpl(percentage) {
    return `
    <div class="progress">
      <div class="progress-bar" role="progressbar" aria-valuenow="` + percentage + `" aria-valuemin="0" aria-valuemax="100" style="min-width: 2em; width: ` + percentage + `%;">
        ` + percentage + `%
      </div>
    </div>
    `
}