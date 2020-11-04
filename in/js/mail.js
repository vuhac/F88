$(document).on('click', '.loadMore', function() {
    var csrftoken = csrf;
    var btnId = $(this).attr('id');
    var tabId = $('.mailbox.active.show').attr('id');
    // console.log(tabId);


    if (tabId === 'inboxTab') {
        var data = getInboxSearchData();
    } else {
        var data = getSentSearchData();
    }

    data.isSearch = ($(this).val() == '1') ? '1' : '0';

    $.ajax({
        type: "POST",
        url: "mail_action.php",
        data: {
            csrftoken: csrftoken,
            data: JSON.stringify(data),
            action: 'loadMore'
        },
    }).done(function(resp) {
        // $('#preview_result').html(resp);
        var res = JSON.parse(resp);

        if (res.status === 'success') {
            var loadData = res.result;
            var loadDataHtml = loadData.reduce(function(accumulator, currentValue, currentIndex, array) {
                accumulator += (tabId === 'inboxTab') ? combineInboxContentHtml(currentValue) : combineSentContentHtml(currentValue);

                return accumulator;
            }, '');

            var tableId = (tabId === 'inboxTab') ? 'inboxContent>tr' : 'sentContent>tr';
            $('#' + tableId + ':last').after(loadDataHtml);

            if ($('#' + tableId).length == res.count) {
                $('#' + btnId).remove();
            }
        } else {
            $('#' + btnId).remove();
            alert(res.result);
        }
    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus);
    });
});

$(document).on('click', '#loadMoreMember', function() {
    var csrftoken = csrf;
    var count = $('#memberListContent>tr').length;
    var buttonVal = $(this).val().split('_');

    $.ajax({
        type: "POST",
        url: "mail_action.php",
        data: {
            csrftoken: csrftoken,
            data: JSON.stringify({
                'count': count,
                'mailCode': buttonVal[0],
                'mailType': buttonVal[1],
                'source': ($('.mailbox.active').attr('id') == 'inboxTab') ? 'inbox' : 'sent'
            }),
            action: 'loadMoreAccList'
        },
    }).done(function(resp) {
        // $('#preview_result').html(resp);
        var res = JSON.parse(resp);

        if (res.status === 'success') {
            var memberListData = res.result.accList;
            var memberListHtml = memberListData.reduce(function(accumulator, currentValue, currentIndex, array) {
                // var isRead = (currentValue.readtime == '') ? lang.unread : lang.read;
                var isRead = (currentValue.readtime == '') ? '-' : lang.read;
                var isDelete = (currentValue.isDelete == 1) ? '-' : lang.state_delete;

                var contentHtml = `
                <tr>
                  <td><a class="btn btn-link" href="./member_account.php?a=` + currentValue.id + `" role="button">` + currentValue.acc + `</a></td>
                  <td>` + isRead + `</td>
                  <td>` + isDelete + `</td>
                </tr>
                `;

                return accumulator + contentHtml;
            }, '');

            $('#memberListContent>tr:last').after(memberListHtml);

            if ($('#memberListContent>tr').length == res.result.count) {
                $('#loadMoreMember').remove();
            }
        } else {
            alert(res.result);
        }
    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus);
    });
});

$(document).on('click', '.search', function() {
    var csrftoken = csrf;
    var tabId = $('.mailbox.active.show').attr('id');

    if (tabId === 'inboxTab') {
        var data = getInboxSearchData();

        $('.delAllInbox').prop('checked', false);
    } else {
        var data = getSentSearchData();

        $('.delAllSent').prop('checked', false);
    }

    $.ajax({
        type: "POST",
        url: "mail_action.php",
        data: {
            csrftoken: csrftoken,
            data: JSON.stringify(data),
            action: 'search'
        },
    }).done(function(resp) {
        // $('#preview_result').html(resp);
        var res = JSON.parse(resp);

        if (res.status === 'success') {
            var searchData = res.result;
            var searchDataHtml = searchData.reduce(function(accumulator, currentValue, currentIndex, array) {
                accumulator += (tabId === 'inboxTab') ? combineInboxContentHtml(currentValue) : combineSentContentHtml(currentValue);

                return accumulator;
            }, '');

            var tableId = (tabId === 'inboxTab') ? 'inboxContent' : 'sentContent';
            var loadBtnId = (tabId === 'inboxTab') ? 'inboxLoadMore' : 'sentLoadMore';
            var table = (tabId === 'inboxTab') ? 'inboxTable' : 'sentTable';

            if ($('#' + loadBtnId).length == 0) {
                $('#' + table).after(`<button type="button" class="btn btn-primary btn-lg btn-block loadMore" id="` + loadBtnId + `">` + lang.loading + `</button>`);
            }

            $('#' + loadBtnId).val('1');
            $('#' + tableId).empty().append(searchDataHtml);

            if ($('#' + tableId + '>tr').length == res.count) {
                $('#' + loadBtnId).remove();
            }
        } else {
            alert(res.result);

            // if (tabId === 'inboxTab') {
            //     $('#inboxContent').empty();
            //     $('#inboxContent').append();
            // }

            // if (tabId === 'sentTab') {
            //     $('#sentContent').empty();
            //     $('#sentContent').append();
            // }
        }
    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus);
    });
});

$(document).on('click', '.memberListlDetail', function() {
    var csrftoken = csrf;
    var buttonVal = $(this).parent().parent().attr('id').split('_');

    $.ajax({
        type: "POST",
        url: "mail_action.php",
        data: {
            csrftoken: csrftoken,
            data: JSON.stringify({
                'mailCode': buttonVal[0],
                'mailType': buttonVal[1],
                'source': ($('.mailbox.active').attr('id') == 'inboxTab') ? 'inbox' : 'sent'
            }),
            action: 'memberList'
        },
    }).done(function(resp) {
        // $('#preview_result').html(resp);

        $('#memberListModalTitle').empty();
        $('#memberListCol').empty();

        $('#memberListContent').empty();
        $('#loadMoreMember').remove();

        if ($('.mailbox.active').attr('id') == 'inboxTab') {
            var title = lang.list_of_sender;
            var col = `
          <tr>
            <th scope="col">` + lang.account + `</th>
          </tr>`;
        } else {
            var title = lang.list_of_receiver;
            var col = `
            <tr>
              <th scope="col">` + lang.account + `</th>
              <th scope="col">` + lang.state_of_reading + `</th>
              <th scope="col">` + lang.state_of_mail + `</th>
            </tr>`;
        }

        var res = JSON.parse(resp);

        if (res.status === 'success') {
            var memberListData = res.result.accList;
            var memberListHtml = memberListData.reduce(function(accumulator, currentValue, currentIndex, array) {
                // var isRead = (currentValue.readtime == '') ? lang.unread : lang.read;
                var isRead = (currentValue.readtime == '') ? '-' : lang.read;
                var isDelete = (currentValue.isDelete == 1) ? '-' : lang.state_delete;

                if ($('.mailbox.active').attr('id') == 'inboxTab') {
                    var contentHtml = `
                    <tr>
                      <td><a class="btn btn-link" href="./member_account.php?a=` + currentValue.id + `" role="button">` + currentValue.acc + `</a></td>
                    </tr>
                    `;
                } else {
                    var contentHtml = `
                    <tr>
                      <td><a class="btn btn-link" href="./member_account.php?a=` + currentValue.id + `" role="button">` + currentValue.acc + `</a></td>
                      <td>` + isRead + `</td>
                      <td>` + isDelete + `</td>
                    </tr>
                    `;
                }

                return accumulator + contentHtml;
            }, '');

            if (res.result.count > 10) {
                $('#memberListTable').after(`<button type="button" class="btn btn-primary btn-lg btn-block" id="loadMoreMember" value="` + buttonVal[0] + `_` + buttonVal[1] + `">` + lang.loading + `</button>`);
            }

            $('#memberListModalTitle').append(title);
            $('#memberListCol').append(col);

            $('#memberListContent').append(memberListHtml);
            $('#memberListModal').modal('show');
        } else {
            alert(res.result);
        }

    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus);
    });
});

$(document).on('click', '.mailDetail', function() {
    var csrftoken = csrf;
    var buttonVal = $(this).parent().parent().attr('id').split('_');
    var source = ($(this).val() == 'inboxMailDetail') ? 'inbox' : 'sent';

    $.ajax({
        type: "POST",
        url: "mail_action.php",
        data: {
            csrftoken: csrftoken,
            data: JSON.stringify({
                'mailCode': buttonVal[0],
                'mailType': buttonVal[1],
                'source': source
            }),
            action: 'content'
        },
    }).done(function(resp) {
        // $('#preview_result').html(resp);
        $('#mailDetailTitle').empty();
        $('#mailDetailContent').empty();
        $('#delMail').val('');

        var res = JSON.parse(resp);

        if (res.status === 'success') {
            if (source == 'inbox') {
                if(!$('#' + res.result.mailcode + '_' + res.result.mailtype).hasClass('unread')) {
                    let nums = Number($('.sysop_bullhorn .badge').text());
                    $('.sysop_bullhorn .badge').text(nums-1);
                    let num = Number($('#announce.announce #pills-mail .view-all span').text());
                    $('#announce.announce #pills-mail .view-all span').text(num-1);

                    for (let i=0;i<$("#announce.announce #pills-mail .data").size(); i++) {
                        if ($("#announce.announce #pills-mail .data").find('.title').eq(i).html() == res.result.subject) {
                            $("#announce.announce #pills-mail .data").eq(i).remove()
                            break
                        }
                    }
                    for (let i=0;i<$("#announce.announce #pills-notify .data").size(); i++) {
                        if ($("#announce.announce #pills-notify .data").find('.title').eq(i).html() == res.result.subject) {
                            $("#announce.announce #pills-notify .data").eq(i).remove()
                            break
                        }
                    }
                }
                $('#' + res.result.mailcode + '_' + res.result.mailtype).addClass('unread');
            }

            $('#delMail').val(res.result.mailcode + `_` + res.result.mailtype);
            $('#mailDetailTitle').append(res.result.subject);
            $('#mailDetailContent').append(combineMailContentHtml(res.result));
            $('#mailDetailModal').modal('show');
        } else {
            alert(res.result);
        }
    }).fail(function(jqXHR, textStatus) {
        alert('Request failed: ' + textStatus);
    });
});

$(document).on('click', '.delMails', function() {
    var csrftoken = csrf;
    var btnId = $(this).attr('id');
    var mails = (btnId == 'delInbox' || btnId == 'delSent') ? $('.' + $(this).val()).serialize() : $(this).val();

    if (confirm(lang.are_you_sure_to_delete_the_mail)) {
        $.ajax({
            type: "POST",
            url: "mail_action.php",
            data: {
                csrftoken: csrftoken,
                data: JSON.stringify({ 'mails': mails }),
                action: 'delete'
            },
        }).done(function(resp) {
            // $('#preview_result').html(resp);
            var res = JSON.parse(resp);

            if (res.status === 'success') {
                alert(res.result);
                location.reload();
            } else {
                alert(res.result);
                location.reload();
            }
        }).fail(function(jqXHR, textStatus) {
            alert('Request failed: ' + textStatus);
        });
    }
});

$(document).on('click', '#markRead, #markUnread', function() {
    var csrftoken = csrf;
    var btnId = $(this).attr('id');
    var mails = $('.delInbox').serialize();

    if (mails != '') {
        $.ajax({
            type: "POST",
            url: "mail_action.php",
            data: {
                csrftoken: csrftoken,
                data: JSON.stringify({
                    'mails': mails,
                    'markAction': btnId
                }),
                action: 'markRead'
            },
        }).done(function(resp) {
            // $('#preview_result').html(resp);
            var res = JSON.parse(resp);

            if (res.status === 'success') {
                res.result.forEach(function(e) {
                    if (!$('#' + e).hasClass('unread') && btnId == 'markRead') {
                        $('#' + e).addClass('unread');

                        let nums = Number($('.sysop_bullhorn .badge').text());
                        $('.sysop_bullhorn .badge').text(nums-1);
                        let num = Number($('#announce.announce #pills-mail .view-all span').text());
                        $('#announce.announce #pills-mail .view-all span').text(num-1);

                        for (let i=0;i<$("#announce.announce #pills-mail .data").size(); i++) {
                            if ($("#announce.announce #pills-mail .data").find('.title').eq(i).html() == $('#' + e +' .mailDetail').html()) {
                                $("#announce.announce #pills-mail .data").eq(i).remove()
                                break
                            }
                        }
                        for (let i=0;i<$("#announce.announce #pills-notify .data").size(); i++) {
                            if ($("#announce.announce #pills-notify .data").find('.title').eq(i).html() == $('#' + e +' .mailDetail').html()) {
                                $("#announce.announce #pills-notify .data").eq(i).remove()
                                break
                            }
                        }
                    }

                    if ($('#' + e).hasClass('unread') && btnId == 'markUnread') {
                        $('#' + e).removeClass('unread');

                        let nums = Number($('.sysop_bullhorn .badge').text());
                        $('.sysop_bullhorn .badge').text(nums+1);
                        let num = Number($('#announce.announce #pills-mail .view-all span').text());
                        $('#announce.announce #pills-mail .view-all span').text(num+1);

                        let subject = $('#' + e +' .mailDetail').html()
                        let time = $('#' + e +' td').eq(3).html()
                        $("#announce.announce #pills-mail").prepend(`<li class="data"><a href="mail.php">
                                                <span class="title">`+subject+`</span>
                                                <span class="subtitle"><span>`+time.substr(0,10)+`</span>
                                                <span>站內信</span></span>
                                            </li>`);

                        $("#announce.announce #pills-notify").prepend(`<li class="data"><a href="mail.php">
                                            <span class="title">`+subject+`</span>
                                            <span class="subtitle"><span>`+time.substr(0,10)+`</span>
                                            <span>站內信</span></span>
                                        </li>`);
                    }
                });
            } else {
                alert(res.result);
            }
        }).fail(function(jqXHR, textStatus) {
            alert('Request failed: ' + textStatus);
        });
    }
});

$(document).on('click', '#downloadMailDetail', function() {
    alert(lang.the_download_is_about_to_begin);
});

$(document).on('change', '.delAllInbox', function() {
    $('.delInbox').prop('checked', $(this).prop('checked'));
});

$(document).on('change', '.delAllSent', function() {
    $('.delSent').prop('checked', $(this).prop('checked'));
});

$(document).on('change', '.delInbox', function() {
    if ($('.delInbox:checked').length != $('.delInbox').length) {
        $('.delAllInbox').prop('checked', false);
    } else {
        $('.delAllInbox').prop('checked', true);
    }
});

$(document).on('change', '.delSent', function() {
    if ($(".delSent:checked").length != $('.delSent').length) {
        $('.delAllSent').prop('checked', false);
    } else {
        $('.delAllSent').prop('checked', true);
    }
});

function getInboxSearchData() {
    var data = {
        'msgfrom': $('#inboxMsgfromAcc').val(),
        'subject': $('#inboxSubject').val(),
        'date': $('#inboxDateSelect').val(),
        'read': ($("#read").prop('checked')) ? 1 : 0,
        'unread': ($("#unread").prop('checked')) ? 1 : 0,
        // 'count': $('#inboxContent>tr').length,
        'count': $('.inboxDataRow').length,
        'source': 'inbox'
    };

    return data;
}

function getSentSearchData() {
    var data = {
        'msgto': $('#sentMsgtoAcc').val(),
        'subject': $('#sentSubject').val(),
        'date': $('#sentDateSelect').val(),
        // 'count': $('#sentContent>tr').length,
        'count': $('.sentDataRow').length,
        'source': 'sent',
    };

    return data;
}

function combineInboxContentHtml(data) {
    var isRead = (data.cs_readtime != '') ? 'unread' : '';
    var idDelete = (data.status == '0') ? 'delete' : '';

    var html = `
    <tr class="inboxDataRow ` + isRead + `" id="` + data.mailcode + `_` + data.mailtype + `">
      <td>
        <div class="form-check">
          <input class="form-check-input position-static delInbox" type="checkbox" name="delMail" value="` + data.mailcode + `_` + data.mailtype + `" aria-label="delMail">
        </div>
      </td>
      <td>
        <button type="button" class="btn btn-link memberListlDetail" value="msgfromDetail">` + data.msgfrom + `</button>
      </td>
      <td>
        <button type="button" class="btn btn-link mailDetail ` + idDelete + `" value="inboxMailDetail">` + data.subject + `</button>
      </td>
      <td>` + data.sendtime + ` <small>(` + data.howlongage + `)</small></td>
    </tr>
    `;

    return html;
}

function combineSentContentHtml(data) {
    var msgto = (data.mailtype === 'group') ? lang.Total + ` ` + data.msgto + ` ` + lang.people : data.msgto;
    var html = `
    <tr class="sentDataRow" id="` + data.mailcode + `_` + data.mailtype + `">
      <td>
        <div class="form-check">
          <input class="form-check-input position-static delSent" name="delMail" type="checkbox" value="` + data.mailcode + `_` + data.mailtype + `" aria-label="delMail">
        </div>
      </td>
      <td>
        <button type="button" class="btn btn-link memberListlDetail" value="msgtoDetail">` + msgto + `</button>
      </td>
      <td>
        <button type="button" class="btn btn-link mailDetail" value="sentMailDetail">` + data.subject + `</button>
      </td>
      <td>` + data.sendtime + ` <small>(` + data.howlongage + `)</small></td>
    </tr>
    `;

    return html;
}

function combineMailContentHtml(data) {
    if (data.mailtype == 'group' && data.template_mail == 1) {
        var html = `
        <form>
          <div class="form-group">
            <div class="d-flex bd-highlight mb-3">
              <div class="mr-auto p-2 bd-highlight">` + data.msgfrom + ` ` + lang.sent_the_mail_to + ` ` + data.msgto + ` ` + lang.on + ` ` + data.sendtime + ` - ` + data.howlongage + `</div>
              <div class="p-2 bd-highlight"><a class="btn btn-link" id="downloadMailDetail" href="sendmail_action.php?a=downloadMailDetail&code=` + data.mailcode + `" role="button">` + lang.download_details_of_mail + `</a></div>
            </div>
          </div>
          <div class="form-group">
            <div class="alert alert-secondary" role="alert">
            ` + data.message + `
            </div>
          </div>
        </form>
        `;
    } else {
        var html = `
      <form>
        <div class="form-group">
          ` + data.msgfrom + ` ` + lang.sent_the_mail_to + ` ` + data.msgto + ` ` + lang.on + ` ` + data.sendtime + ` - ` + data.howlongage + `
        </div>
        <div class="form-group">
          <div class="alert alert-secondary" role="alert">
          ` + data.message + `
          </div>
        </div>
      </form>
      `;
    }

    return html;
}